<?php

namespace App\Domain\Fraud;

use App\Domain\Activity\DailyActivityAggregator;
use App\Domain\Fraud\Data\RuleResult;
use App\Domain\Fraud\Data\SessionContext;
use App\Domain\Settings\Settings;
use App\Enums\FraudCaseStatus;
use App\Enums\SessionKind;
use App\Enums\SessionStatus;
use App\Events\WalkingSessionScored;
use App\Models\FraudCase;
use App\Models\FraudEvent;
use App\Models\WalkingSession;
use Illuminate\Support\Facades\DB;

/**
 * Multi-signal scoring of a walking session (docs/phase-0/05-flows.md §5.3).
 *
 *   fraud_risk     = min(100, Σ risk_i × weight_i / 100)      (hard reject → 100)
 *   verified_steps = min(Σ_b min(steps_b, caps_b), session caps)
 *   confidence     = 100 − risk − data-quality penalties
 *
 * Status: rejected (risk ≥ reject) · under_review (risk ≥ review, opens a case)
 *         · partially_verified (caps applied) · verified.
 */
class FraudEngine
{
    public function __construct(
        private readonly RuleRegistry $rules,
        private readonly Settings $settings,
        private readonly DailyActivityAggregator $daily,
    ) {}

    public function score(WalkingSession $session, bool $force = false): WalkingSession
    {
        if ($session->scored_at !== null && ! $force) {
            return $session;
        }

        $context = SessionContext::load($session);
        $results = [];
        foreach ($this->rules->active() as ['rule' => $rule, 'weight' => $weight, 'params' => $params]) {
            $result = $rule->evaluate($context, $params);
            if ($result->triggered()) {
                $results[$rule->key()] = [$result, $weight];
            }
        }

        [$risk, $verified, $hardReject] = $this->combine($context, $results);
        $confidence = $this->confidence($context, $risk);

        $status = match (true) {
            $hardReject || $risk >= $this->settings->int('fraud.reject_threshold') => SessionStatus::Rejected,
            $risk >= $this->settings->int('fraud.review_threshold') => SessionStatus::UnderReview,
            $verified < $session->raw_steps => SessionStatus::PartiallyVerified,
            default => SessionStatus::Verified,
        };
        if ($status === SessionStatus::Rejected) {
            $verified = 0;
        }

        DB::transaction(function () use ($session, $results, $risk, $verified, $confidence, $status) {
            $session->forceFill([
                'verified_steps' => $verified,
                'fraud_score' => $risk,
                'confidence_score' => $confidence,
                'status' => $status,
                'rule_set_version' => $this->settings->int('fraud.rule_set_version'),
                'scored_at' => now(),
            ])->save();

            FraudEvent::query()->where('subject_type', $session->getMorphClass())->where('subject_id', $session->id)->delete();
            foreach ($results as $key => [$result, $weight]) {
                FraudEvent::query()->create([
                    'user_id' => $session->user_id,
                    'device_id' => $session->device_id,
                    'subject_type' => $session->getMorphClass(),
                    'subject_id' => $session->id,
                    'rule_key' => $key,
                    'score' => min(100, (int) round($result->risk * $weight / 100)),
                    'severity' => $this->severity($result),
                    'details' => array_filter([
                        'evidence' => $result->evidence ?: null,
                        'capped_buckets' => $result->bucketCaps === [] ? null : count($result->bucketCaps),
                        'session_cap' => $result->sessionCap,
                    ], fn ($v) => $v !== null),
                ]);
            }

            if ($status === SessionStatus::UnderReview) {
                FraudCase::query()->firstOrCreate(
                    ['subject_type' => $session->getMorphClass(), 'subject_id' => $session->id],
                    [
                        'user_id' => $session->user_id,
                        'device_id' => $session->device_id,
                        'risk_score' => $risk,
                        'status' => FraudCaseStatus::Open,
                        'reason' => 'ریسک بالا: '.implode('، ', array_keys($results)),
                    ],
                );
            }

            $this->daily->refresh($session->user, $session->local_date->toDateString());
        });

        WalkingSessionScored::dispatch($session);

        return $session;
    }

    public function refreshDaily(WalkingSession $session): void
    {
        $session->loadMissing('user.profile');
        $this->daily->refresh($session->user, $session->local_date->toDateString());
    }

    /**
     * @param  array<string, array{0: RuleResult, 1: int}>  $results
     * @return array{0: int, 1: int, 2: bool}
     */
    private function combine(SessionContext $context, array $results): array
    {
        $risk = 0.0;
        $hardReject = false;
        $bucketCaps = [];
        $sessionCap = null;

        foreach ($results as [$result, $weight]) {
            $risk += $result->risk * $weight / 100;
            $hardReject = $hardReject || $result->hardReject;
            foreach ($result->bucketCaps as $i => $cap) {
                $bucketCaps[$i] = min($bucketCaps[$i] ?? PHP_INT_MAX, $cap);
            }
            if ($result->sessionCap !== null) {
                $sessionCap = min($sessionCap ?? PHP_INT_MAX, $result->sessionCap);
            }
        }

        $verified = 0;
        foreach ($context->buckets as $i => $b) {
            $verified += min($b->steps, $bucketCaps[$i] ?? PHP_INT_MAX);
        }
        if ($sessionCap !== null) {
            $verified = min($verified, max(0, $sessionCap));
        }

        return [$hardReject ? 100 : (int) min(100, round($risk)), $verified, $hardReject];
    }

    private function confidence(SessionContext $context, int $risk): int
    {
        $penalty = 0;
        if ($context->session->kind === SessionKind::Passive) {
            $penalty += 10; // coarse data: less evidence either way
        } elseif ($context->motion('detector_ratio') === null) {
            $penalty += 5;
        }

        return max(0, min(100, 100 - $risk - $penalty));
    }

    private function severity(RuleResult $result): string
    {
        return match (true) {
            $result->hardReject || $result->risk >= 60 => 'high',
            $result->risk >= 30 => 'medium',
            $result->risk > 0 => 'low',
            default => 'info',
        };
    }
}
