<?php

namespace App\Domain\Social;

use App\Domain\Settings\FeatureFlags;
use App\Exceptions\ApiException;
use App\Models\DailyActivity;
use App\Models\FriendChallenge;
use App\Models\FriendChallengeMember;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Friends and friendly step races. Only accepted friends see each other's
 * verified steps (accepting is the consent); nothing here pays points, so
 * there is nothing to gain from fake friends or collusion.
 */
class SocialService
{
    public const MAX_FRIENDS = 100;

    public const MAX_MEMBERS = 10;

    public const MAX_ACTIVE_CREATED = 3;

    public function __construct(private readonly FeatureFlags $flags) {}

    private function guard(User $user): void
    {
        if (! $this->flags->enabled('friends', $user->id)) {
            throw ApiException::forbidden('feature_disabled', 'بخش دوستان در حال حاضر فعال نیست.');
        }
    }

    // ---- Friends ----------------------------------------------------------

    public function request(User $user, string $code): Friendship
    {
        $this->guard($user);
        $other = User::query()->where('referral_code', strtoupper(trim($code)))->first();
        if ($other === null || $other->id === $user->id) {
            throw ApiException::unprocessable('friend_not_found', 'کاربری با این کد پیدا نشد.');
        }
        if ($this->friendIds($user)->count() >= self::MAX_FRIENDS) {
            throw ApiException::conflict('friend_limit', 'حداکثر '.self::MAX_FRIENDS.' دوست می‌توانی داشته باشی.');
        }

        [$low, $high] = Friendship::pair($user->id, $other->id);
        $existing = Friendship::query()->where(['user_low_id' => $low, 'user_high_id' => $high])->first();
        if ($existing !== null) {
            // They had already asked us: asking back is accepting.
            if ($existing->status === Friendship::PENDING && $existing->requested_by === $other->id) {
                return $this->accept($user, $existing);
            }

            return $existing;
        }

        try {
            $friendship = Friendship::query()->create(['user_low_id' => $low, 'user_high_id' => $high, 'requested_by' => $user->id, 'status' => Friendship::PENDING]);
        } catch (UniqueConstraintViolationException) {
            return Friendship::query()->where(['user_low_id' => $low, 'user_high_id' => $high])->firstOrFail();
        }
        $other->notify(new UserNotification('challenge', 'درخواست دوستی', $user->publicName().' می‌خواهد با تو دوست شود.', ['type' => 'friend']));

        return $friendship;
    }

    public function accept(User $user, Friendship $friendship): Friendship
    {
        $this->assertParty($user, $friendship);
        if ($friendship->status === Friendship::PENDING && $friendship->requested_by !== $user->id) {
            $friendship->forceFill(['status' => Friendship::ACCEPTED, 'accepted_at' => now()])->save();
            User::query()->find($friendship->requested_by)?->notify(new UserNotification('challenge', 'درخواست دوستی پذیرفته شد',
                $user->publicName().' حالا دوست توست.', ['type' => 'friend']));
        }

        return $friendship;
    }

    /** Declines, cancels or unfriends. Leaves shared races untouched (they end on their own). */
    public function remove(User $user, Friendship $friendship): void
    {
        $this->assertParty($user, $friendship);
        $friendship->delete();
    }

    private function assertParty(User $user, Friendship $f): void
    {
        if ($f->user_low_id !== $user->id && $f->user_high_id !== $user->id) {
            abort(404);
        }
    }

    /** @return Collection<int, int> */
    public function friendIds(User $user): Collection
    {
        return Friendship::query()->where('status', Friendship::ACCEPTED)
            ->where(fn ($q) => $q->where('user_low_id', $user->id)->orWhere('user_high_id', $user->id))
            ->get()->map(fn (Friendship $f) => $f->otherThan($user->id));
    }

    public function areFriends(User $a, int $bId): bool
    {
        [$low, $high] = Friendship::pair($a->id, $bId);

        return Friendship::query()->where(['user_low_id' => $low, 'user_high_id' => $high, 'status' => Friendship::ACCEPTED])->exists();
    }

    /** Friends ranked by this week's verified steps, the viewer included. */
    public function overview(User $user): array
    {
        $this->guard($user);
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $weekStart = $today->subDays(($today->dayOfWeek + 1) % 7);

        $accepted = Friendship::query()->where('status', Friendship::ACCEPTED)
            ->where(fn ($q) => $q->where('user_low_id', $user->id)->orWhere('user_high_id', $user->id))->get()
            ->keyBy(fn (Friendship $f) => $f->otherThan($user->id));
        $ids = $accepted->keys();
        $steps = $this->steps([...$ids->all(), $user->id], $weekStart, $today);
        $users = User::query()->whereIn('id', $ids->all())->get()->keyBy('id');

        $pending = Friendship::query()->where('status', Friendship::PENDING)
            ->where(fn ($q) => $q->where('user_low_id', $user->id)->orWhere('user_high_id', $user->id))->get();
        $pendingUsers = User::query()->whereIn('id', $pending->map(fn ($f) => $f->otherThan($user->id)))->get()->keyBy('id');

        $ranking = $users->values()->push($user)
            ->map(fn (User $u) => [...$this->card($u), 'week_steps' => (int) ($steps[$u->id] ?? 0), 'is_me' => $u->id === $user->id,
                'friendship_id' => $accepted[$u->id]?->id ?? null])
            ->sortByDesc('week_steps')->values()->all();

        return [
            'code' => $user->referral_code,
            'ranking' => $ranking,
            'incoming' => $pending->filter(fn ($f) => $f->requested_by !== $user->id)
                ->map(fn ($f) => ['id' => $f->id, ...$this->card($pendingUsers[$f->otherThan($user->id)])])->values()->all(),
            'outgoing' => $pending->filter(fn ($f) => $f->requested_by === $user->id)
                ->map(fn ($f) => ['id' => $f->id, ...$this->card($pendingUsers[$f->otherThan($user->id)])])->values()->all(),
        ];
    }

    private function card(User $u): array
    {
        return [
            'user_id' => $u->public_id,
            'name' => $u->publicName(),
            'avatar_url' => $u->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
            'level' => $u->level ?? 1,
        ];
    }

    /** @return array<int, int> user id => verified steps between the dates (inclusive) */
    private function steps(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DailyActivity::query()->whereIn('user_id', $userIds)->whereBetween('local_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('user_id')->selectRaw('user_id, SUM(verified_steps) as s')->pluck('s', 'user_id')->map(fn ($v) => (int) $v)->all();
    }

    // ---- Friendly races ----------------------------------------------------

    /** @param  list<string>  $friendPublicIds */
    public function createChallenge(User $user, string $title, int $days, array $friendPublicIds): FriendChallenge
    {
        $this->guard($user);
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $active = FriendChallenge::query()->where('creator_id', $user->id)->where('ends_on', '>=', $today->toDateString())->count();
        if ($active >= self::MAX_ACTIVE_CREATED) {
            throw ApiException::conflict('challenge_limit', 'هم‌زمان حداکثر '.self::MAX_ACTIVE_CREATED.' رقابت می‌توانی بسازی.');
        }
        $friends = User::query()->whereIn('public_id', array_unique($friendPublicIds))->get();
        if ($friends->isEmpty() || $friends->count() + 1 > self::MAX_MEMBERS) {
            throw ApiException::unprocessable('members_invalid', 'بین ۱ تا '.(self::MAX_MEMBERS - 1).' دوست دعوت کن.');
        }
        foreach ($friends as $f) {
            if (! $this->areFriends($user, $f->id)) {
                throw ApiException::unprocessable('not_friends', 'فقط دوستانت را می‌توانی دعوت کنی.');
            }
        }

        return DB::transaction(function () use ($user, $title, $days, $friends, $today) {
            // Starts tomorrow so everyone begins from zero on a full day.
            $challenge = FriendChallenge::query()->create([
                'creator_id' => $user->id, 'title' => $title,
                'starts_on' => $today->addDay()->toDateString(), 'ends_on' => $today->addDays($days)->toDateString(),
            ]);
            FriendChallengeMember::query()->create(['friend_challenge_id' => $challenge->id, 'user_id' => $user->id, 'status' => FriendChallengeMember::JOINED, 'joined_at' => now()]);
            foreach ($friends as $f) {
                FriendChallengeMember::query()->create(['friend_challenge_id' => $challenge->id, 'user_id' => $f->id, 'status' => FriendChallengeMember::INVITED]);
                $f->notify(new UserNotification('challenge', 'دعوت به رقابت دوستانه', $user->publicName().' تو را به «'.$title.'» دعوت کرد.',
                    ['type' => 'friend_challenge', 'id' => $challenge->public_id]));
            }

            return $challenge;
        });
    }

    public function respond(User $user, FriendChallenge $challenge, bool $join): void
    {
        $member = $challenge->members()->where('user_id', $user->id)->first() ?? abort(404);
        if ($challenge->ends_on->lt(CarbonImmutable::now($user->timezone)->startOfDay())) {
            throw ApiException::conflict('challenge_ended', 'این رقابت تمام شده است.');
        }
        $member->forceFill($join
            ? ['status' => FriendChallengeMember::JOINED, 'joined_at' => $member->joined_at ?? now()]
            : ['status' => FriendChallengeMember::LEFT])->save();
    }

    /** @return list<array<string, mixed>> */
    public function challenges(User $user): array
    {
        $this->guard($user);
        $since = CarbonImmutable::now($user->timezone)->subDays(14)->toDateString();

        return FriendChallenge::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', [FriendChallengeMember::INVITED, FriendChallengeMember::JOINED]))
            ->where('ends_on', '>=', $since)->orderByDesc('starts_on')->limit(20)->get()
            ->map(fn (FriendChallenge $c) => $this->present($user, $c, standings: false))->all();
    }

    public function present(User $user, FriendChallenge $c, bool $standings = true): array
    {
        $c->loadMissing('members.user', 'creator');
        $me = $c->members->firstWhere('user_id', $user->id) ?? abort(404);
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $status = $today->lt($c->starts_on) ? 'upcoming' : ($today->gt($c->ends_on) ? 'finished' : 'running');

        $data = [
            'id' => $c->public_id,
            'title' => $c->title,
            'creator' => $c->creator->publicName(),
            'starts_on' => $c->starts_on->toDateString(),
            'ends_on' => $c->ends_on->toDateString(),
            'status' => $status,
            'my_status' => $me->status,
            'members' => $c->members->where('status', FriendChallengeMember::JOINED)->count(),
        ];
        if (! $standings) {
            return $data;
        }

        $joined = $c->members->where('status', FriendChallengeMember::JOINED);
        $to = $today->lt($c->ends_on) ? $today : CarbonImmutable::parse($c->ends_on);
        $steps = $status === 'upcoming' ? [] : $this->steps($joined->pluck('user_id')->all(), CarbonImmutable::parse($c->starts_on), $to);

        return $data + [
            'standings' => $joined->map(fn (FriendChallengeMember $m) => [...$this->card($m->user), 'steps' => (int) ($steps[$m->user_id] ?? 0), 'is_me' => $m->user_id === $user->id])
                ->sortByDesc('steps')->values()->all(),
            'invited' => $c->members->where('status', FriendChallengeMember::INVITED)->map(fn ($m) => $m->user->publicName())->values()->all(),
        ];
    }
}
