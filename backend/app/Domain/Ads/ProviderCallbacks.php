<?php

namespace App\Domain\Ads;

use App\Models\AdProvider;
use Illuminate\Http\Request;

/**
 * Server-to-server reward callbacks from external ad networks.
 *
 * Contract (configured per provider in the admin panel):
 *   POST /api/v1/webhooks/ads/{provider}
 *   X-Signature: hex(HMAC-SHA256(webhook_secret, raw body))
 *   {"view_id": "<ad view public id we issued>", "transaction_id": "<provider's unique id>"}
 *
 * Yektanet and AdSell adapters must be mapped to this contract once their
 * official S2S documentation is verified; until then these providers stay
 * disabled and every callback is refused (docs/phase-6.md).
 */
class ProviderCallbacks
{
    /** @return array{view_id: string, transaction_id: string}|null */
    public function verify(AdProvider $provider, Request $request): ?array
    {
        if (! $provider->is_enabled || $provider->isInternal() || blank($provider->webhook_secret)) {
            return null;
        }
        $expected = hash_hmac('sha256', $request->getContent(), $provider->webhook_secret);
        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            return null;
        }
        $viewId = $request->json('view_id');
        $tx = $request->json('transaction_id');

        return is_string($viewId) && is_string($tx) && $tx !== '' && strlen($tx) <= 128 ? ['view_id' => $viewId, 'transaction_id' => $tx] : null;
    }
}
