<?php

namespace App\Domain\Ads;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class AdEventRecorder
{
    public function __construct(private readonly ServeToken $tokens) {}

    /**
     * @param  list<array{token: string, type: string}>  $events
     * @return array{accepted: int, rejected: int}
     */
    public function record(User $user, array $events): array
    {
        $accepted = 0;
        foreach ($events as $event) {
            $claims = $this->tokens->verify($event['token']);
            if ($claims === null || $claims['u'] !== $user->id) {
                continue;
            }
            // A click only counts for an ad that was actually displayed.
            if ($event['type'] === 'click' && ! AdEvent::query()->where('serve_id', $claims['s'])->where('type', 'impression')->exists()) {
                continue;
            }
            try {
                AdEvent::query()->create([
                    'ad_id' => $claims['a'], 'ad_campaign_id' => $claims['c'], 'ad_placement_id' => $claims['p'], 'user_id' => $user->id,
                    'type' => $event['type'], 'serve_id' => $claims['s'], 'local_date' => $claims['d'],
                ]);
            } catch (UniqueConstraintViolationException) {
                continue; // duplicate report
            }
            AdCampaign::query()->whereKey($claims['c'])->increment($event['type'] === 'click' ? 'clicks_count' : 'impressions_count');
            $accepted++;
        }

        return ['accepted' => $accepted, 'rejected' => count($events) - $accepted];
    }
}
