<?php

namespace App\Domain\Challenge;

use App\Domain\Gamification\XpService;
use App\Domain\Wallet\WalletService;
use App\Enums\ChallengeStatus;
use App\Enums\ChallengeType;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\DailyActivity;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\WalkingSession;
use App\Notifications\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Progress only counts VERIFIED activity inside the window, from the later of
 * the challenge start and the moment the user joined.
 */
class ChallengeService
{
    public function __construct(private readonly WalletService $wallet, private readonly XpService $xp) {}

    public function join(User $user, Challenge $challenge): ChallengeParticipant
    {
        $participant = DB::transaction(function () use ($user, $challenge) {
            $locked = Challenge::query()->whereKey($challenge->id)->lockForUpdate()->firstOrFail();
            $existing = ChallengeParticipant::query()->where('challenge_id', $locked->id)->where('user_id', $user->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($locked->organization_id !== null && ! OrganizationMember::query()->where('user_id', $user->id)->where('organization_id', $locked->organization_id)->exists()) {
                throw ApiException::forbidden('organization_only', 'این چالش مخصوص اعضای یک سازمان است.');
            }
            if (! $locked->isJoinable()) {
                throw ApiException::unprocessable('challenge_closed', 'امکان پیوستن به این چالش وجود ندارد.');
            }
            $locked->increment('participants_count');

            return ChallengeParticipant::query()->create(['challenge_id' => $locked->id, 'user_id' => $user->id, 'joined_at' => now()]);
        });

        $this->updateParticipant($participant->load('challenge'), $user);

        return $participant->fresh();
    }

    /** Recomputes all of the user's open participations. */
    public function refreshFor(User $user): void
    {
        ChallengeParticipant::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->whereHas('challenge', fn ($q) => $q->where('status', ChallengeStatus::Active)->where('ends_at', '>', now()->subDays(8)))
            ->with('challenge')
            ->get()
            ->each(fn (ChallengeParticipant $p) => $this->updateParticipant($p, $user));
    }

    private function updateParticipant(ChallengeParticipant $p, User $user): void
    {
        if ($p->completed_at !== null) {
            return;
        }
        $c = $p->challenge;
        $from = CarbonImmutable::instance(max($c->starts_at, $p->joined_at))->setTimezone($user->timezone);
        $to = CarbonImmutable::instance($c->ends_at)->setTimezone($user->timezone);
        $range = [$from->toDateString(), $to->toDateString()];

        $progress = match ($c->type) {
            ChallengeType::Distance => (int) WalkingSession::query()->where('user_id', $user->id)->whereBetween('started_at', [$from->utc(), $to->utc()])
                ->whereIn('status', ['verified', 'partially_verified'])
                ->selectRaw('COALESCE(SUM(CASE WHEN raw_steps > 0 THEN distance_m * verified_steps / raw_steps ELSE 0 END),0) d')->value('d'),
            ChallengeType::Daily => (int) DailyActivity::query()->where('user_id', $user->id)->whereBetween('local_date', $range)->max('verified_steps'),
            ChallengeType::Location => $this->locationProgress($c, $user, $from, $to),
            default => (int) WalkingSession::query()->where('user_id', $user->id)->whereBetween('started_at', [$from->utc(), $to->utc()])
                ->whereIn('status', ['verified', 'partially_verified'])->sum('verified_steps'),
        };

        $p->forceFill(['progress' => $progress])->save();

        if ($progress >= $c->target_value) {
            $this->complete($p, $user);
        }
    }

    private function complete(ChallengeParticipant $p, User $user): void
    {
        $updated = ChallengeParticipant::query()->whereKey($p->id)->whereNull('completed_at')->update(['completed_at' => now()]);
        if ($updated === 0) {
            return; // someone else completed it concurrently
        }
        $c = $p->challenge;
        if ($c->reward_points > 0) {
            $this->wallet->hold($user, $c->reward_points, TransactionType::ChallengeReward, 'challenge:'.$c->id, 'پاداش چالش: '.$c->title, $c);
        }
        $this->xp->award($user, $c->reward_xp, 'challenge', 'challenge:'.$c->id);
        ChallengeParticipant::query()->whereKey($p->id)->update(['rewarded_at' => now()]);
        $user->notify(new UserNotification('challenge', 'چالش کامل شد!', 'تبریک! چالش «'.$c->title.'» را به پایان رساندی.', ['type' => 'challenge', 'id' => $c->public_id]));
    }

    /** Verified sponsor visits during the window (Phase 5 visits table). */
    private function locationProgress(Challenge $c, User $user, CarbonImmutable $from, CarbonImmutable $to): int
    {
        if (! $c->campaign_id || ! DB::getSchemaBuilder()->hasTable('visits')) {
            return 0;
        }

        return DB::table('visits')->where('user_id', $user->id)->where('campaign_id', $c->campaign_id)
            ->whereIn('status', ['verified', 'rewarded'])->whereBetween('created_at', [$from->utc(), $to->utc()])->count();
    }

    /** Marks finished challenges as ended (scheduled). */
    public function closeFinished(): int
    {
        return Challenge::query()->where('status', ChallengeStatus::Active)->where('ends_at', '<=', now())->update(['status' => ChallengeStatus::Ended]);
    }
}
