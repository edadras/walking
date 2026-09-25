<?php

namespace App\Domain\Organization;

use App\Domain\Audit\AuditLogger;
use App\Enums\ChallengeStatus;
use App\Exceptions\ApiException;
use App\Models\Challenge;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Company wellness programme: employees join with the company code, see a
 * colleagues-only weekly ranking and company challenges. The company sees
 * aggregates (participation, averages, departments), never individual health data.
 */
class OrganizationService
{
    /** Smallest group whose aggregate the company may see. */
    public const MIN_GROUP = 3;

    public function __construct(private readonly AuditLogger $audit) {}

    public static function newJoinCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Organization::query()->where('join_code', $code)->exists());

        return $code;
    }

    public function membership(User $user): ?OrganizationMember
    {
        return OrganizationMember::query()->with('organization')->where('user_id', $user->id)->first();
    }

    public function join(User $user, string $code, ?string $department): OrganizationMember
    {
        $org = Organization::query()->where('join_code', strtoupper(trim($code)))->first()
            ?? throw ApiException::unprocessable('organization_not_found', 'سازمانی با این کد پیدا نشد.');
        if (! $org->isActive()) {
            throw ApiException::conflict('organization_inactive', 'اشتراک این سازمان فعال نیست؛ با واحد منابع انسانی تماس بگیر.');
        }
        if ($department !== null && $org->departments && ! in_array($department, $org->departments, true)) {
            throw ApiException::unprocessable('department_invalid', 'واحد انتخاب‌شده معتبر نیست.');
        }

        return DB::transaction(function () use ($user, $org, $department) {
            Organization::query()->whereKey($org->id)->lockForUpdate()->first(); // seat count race
            $existing = OrganizationMember::query()->where('user_id', $user->id)->first();
            if ($existing !== null && $existing->organization_id !== $org->id) {
                throw ApiException::conflict('already_member', 'تو عضو سازمان دیگری هستی؛ اول از آن خارج شو.');
            }
            if ($existing === null && $org->members()->count() >= $org->seats) {
                throw ApiException::conflict('organization_full', 'ظرفیت اعضای این سازمان تکمیل است.');
            }
            $member = OrganizationMember::query()->updateOrCreate(['user_id' => $user->id], [
                'organization_id' => $org->id, 'department' => $department, 'joined_at' => $existing?->joined_at ?? now(),
            ]);
            $this->audit->log('organization.joined', $org, meta: ['user' => $user->public_id]);

            return $member->load('organization');
        });
    }

    public function setDepartment(User $user, string $department): void
    {
        $member = $this->membership($user) ?? throw ApiException::conflict('not_member', 'عضو سازمانی نیستی.');
        if (! in_array($department, $member->organization->departments ?? [], true)) {
            throw ApiException::unprocessable('department_invalid', 'واحد انتخاب‌شده معتبر نیست.');
        }
        $member->update(['department' => $department]);
    }

    public function leave(User $user): void
    {
        $member = OrganizationMember::query()->where('user_id', $user->id)->first();
        if ($member !== null) {
            $member->delete();
            $this->audit->log('organization.left', $member->organization, meta: ['user' => $user->public_id]);
        }
    }

    /** @return array{0: string, 1: string} Saturday–Friday of the current Iranian week (local dates). */
    public static function week(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('Asia/Tehran');
        $start = $now->startOfWeek(Carbon::SATURDAY);

        return [$start->toDateString(), $start->addDays(6)->toDateString()];
    }

    /** Colleagues ranking by verified steps this week. */
    public function ranking(Organization $org, int $limit = 30): array
    {
        [$from, $to] = self::week();

        return DB::table('organization_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->leftJoin('daily_activities as d', fn ($j) => $j->on('d.user_id', '=', 'm.user_id')->whereBetween('d.local_date', [$from, $to]))
            ->where('m.organization_id', $org->id)
            ->groupBy('m.user_id', 'u.public_id', 'u.display_name', 'm.department')
            ->selectRaw('m.user_id, u.public_id, u.display_name, m.department, COALESCE(SUM(d.verified_steps), 0) as steps')
            ->orderByDesc('steps')->orderBy('m.user_id')
            ->limit($limit)->get()
            ->values()
            ->map(fn ($r, $i) => ['rank' => $i + 1, 'user_id' => $r->public_id, 'name' => $r->display_name ?: 'همکار', 'department' => $r->department, 'steps' => (int) $r->steps, '_id' => $r->user_id])
            ->all();
    }

    /** Average steps per member this week, per department. */
    public function departments(Organization $org): array
    {
        [$from, $to] = self::week();
        $totals = DB::table('organization_members as m')
            ->leftJoin('daily_activities as d', fn ($j) => $j->on('d.user_id', '=', 'm.user_id')->whereBetween('d.local_date', [$from, $to]))
            ->where('m.organization_id', $org->id)
            ->groupBy('m.department')
            ->selectRaw("COALESCE(m.department, 'بدون واحد') as department, COUNT(DISTINCT m.user_id) as members, COALESCE(SUM(d.verified_steps), 0) as steps")
            ->get();

        // Groups smaller than 3 would expose individuals' activity to the employer: no average for them.
        return $totals->map(fn ($r) => ['department' => $r->department, 'members' => (int) $r->members,
            'avg_steps' => $r->members >= self::MIN_GROUP ? (int) round($r->steps / $r->members) : null])
            ->sortByDesc('avg_steps')->values()->all();
    }

    public function overview(User $user): ?array
    {
        $member = $this->membership($user);
        if ($member === null) {
            return null;
        }
        $org = $member->organization;
        $ranking = $this->ranking($org, 1000);
        $me = collect($ranking)->firstWhere('_id', $user->id);
        [$from, $to] = self::week();

        return [
            'id' => $org->public_id,
            'name' => $org->name,
            'active' => $org->isActive(),
            'department' => $member->department,
            'departments_list' => $org->departments ?? [],
            'members' => count($ranking),
            'week' => ['from' => $from, 'to' => $to],
            'my_rank' => $me['rank'] ?? null,
            'my_steps' => $me['steps'] ?? 0,
            'ranking' => array_map(fn ($r) => [...array_diff_key($r, ['_id' => 1]), 'is_me' => $r['_id'] === $user->id], array_slice($ranking, 0, 30)),
            'departments' => $this->departments($org),
            'challenges' => Challenge::query()->where('organization_id', $org->id)->where('status', ChallengeStatus::Active)
                ->where('ends_at', '>', now())->orderBy('ends_at')->get()
                ->map(fn (Challenge $c) => ['id' => $c->public_id, 'title' => $c->title, 'ends_at' => $c->ends_at->toIso8601String()])->all(),
        ];
    }

    /** Aggregates for the company panel; no per-person health data. */
    public function stats(Organization $org): array
    {
        $members = $org->members()->count();
        $since = now('Asia/Tehran')->subDays(7)->toDateString();
        $active = DB::table('daily_activities')->whereIn('user_id', $org->members()->select('user_id'))
            ->where('local_date', '>=', $since)->where('verified_steps', '>', 0)->distinct()->count('user_id');
        $avg = (int) round((float) DB::table('daily_activities')->whereIn('user_id', $org->members()->select('user_id'))
            ->where('local_date', '>=', $since)->sum('verified_steps') / max(1, $members) / 7);

        $weeks = [];
        for ($w = 7; $w >= 0; $w--) {
            [$from, $to] = self::week(CarbonImmutable::now('Asia/Tehran')->subWeeks($w));
            $sum = (int) DB::table('daily_activities')->whereIn('user_id', $org->members()->select('user_id'))->whereBetween('local_date', [$from, $to])->sum('verified_steps');
            $weeks[$from] = (int) round($sum / max(1, $members) / 7);
        }

        if ($members < self::MIN_GROUP) {
            $avg = null;
            $weeks = array_map(fn () => null, $weeks);
        }

        return ['members' => $members, 'seats' => $org->seats, 'active_7d' => $active, 'participation' => $members ? (int) round($active * 100 / $members) : 0,
            'avg_daily_steps' => $avg, 'weekly_avg_daily_steps' => $weeks];
    }
}
