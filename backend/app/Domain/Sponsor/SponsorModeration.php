<?php

namespace App\Domain\Sponsor;

use App\Domain\Audit\AuditLogger;
use App\Enums\CampaignStatus;
use App\Enums\CouponStatus;
use App\Enums\LocationStatus;
use App\Enums\SponsorStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Every sponsor-side object goes live only after an admin with
 * `sponsors.manage` approves it. All transitions are audited.
 */
class SponsorModeration
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approveSponsor(Sponsor $sponsor, Admin $admin): void
    {
        $this->transition($sponsor, ['status' => SponsorStatus::Approved, 'approved_by' => $admin->id, 'approved_at' => now(), 'rejection_reason' => null], 'sponsor.approved', $admin);
    }

    public function rejectSponsor(Sponsor $sponsor, string $reason, Admin $admin): void
    {
        $this->transition($sponsor, ['status' => SponsorStatus::Rejected, 'rejection_reason' => $reason], 'sponsor.rejected', $admin);
    }

    /** Suspension also pauses every running campaign of the sponsor. */
    public function suspendSponsor(Sponsor $sponsor, string $reason, Admin $admin): void
    {
        DB::transaction(function () use ($sponsor, $reason, $admin) {
            $this->transition($sponsor, ['status' => SponsorStatus::Suspended, 'rejection_reason' => $reason], 'sponsor.suspended', $admin);
            $sponsor->campaigns()->where('status', CampaignStatus::Active)->update(['status' => CampaignStatus::Paused]);
        });
    }

    /** Adds paid points to the sponsor budget (the payment itself is settled outside the app). */
    public function topUp(Sponsor $sponsor, int $points, string $note, Admin $admin): void
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Top-up must be positive.');
        }
        DB::table('sponsors')->where('id', $sponsor->id)->increment('point_budget', $points);
        $this->audit->log('sponsor.budget_topped_up', $sponsor, ['point_budget' => $sponsor->point_budget], ['point_budget' => $sponsor->point_budget + $points], ['points' => $points, 'note' => $note], $admin);
        $sponsor->refresh();
    }

    public function approve(Location|Campaign|Coupon $subject, Admin $admin): void
    {
        $status = match (true) {
            $subject instanceof Location => LocationStatus::Approved,
            $subject instanceof Campaign => CampaignStatus::Active,
            $subject instanceof Coupon => CouponStatus::Active,
        };
        $extra = $subject instanceof Campaign ? ['approved_by' => $admin->id, 'approved_at' => now()] : [];
        $this->transition($subject, ['status' => $status, 'rejection_reason' => null, ...$extra], $subject->getMorphClass().'.approved', $admin);
    }

    public function reject(Location|Campaign|Coupon $subject, string $reason, Admin $admin): void
    {
        $status = match (true) {
            $subject instanceof Location => LocationStatus::Rejected,
            $subject instanceof Campaign => CampaignStatus::Rejected,
            $subject instanceof Coupon => CouponStatus::Rejected,
        };
        $this->transition($subject, ['status' => $status, 'rejection_reason' => $reason], $subject->getMorphClass().'.rejected', $admin);
    }

    private function transition(Model $subject, array $changes, string $action, Model $actor): void
    {
        $old = array_intersect_key($subject->getAttributes(), $changes);
        $subject->forceFill($changes)->save();
        $this->audit->log($action, $subject, $old, array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $changes), actor: $actor);
    }
}
