<?php

namespace App\Domain\Wallet;

use App\Enums\TransactionStatus;
use App\Models\PointTransaction;
use App\Models\User;

class WalletSummary
{
    public function __construct(private readonly WalletService $wallets, private readonly ConversionRate $rates) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $wallet = $this->wallets->walletFor($user);
        $rate = $this->rates->current();
        $nextRelease = PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('status', TransactionStatus::Pending)
            ->where('amount', '>', 0)
            ->min('available_at');

        return [
            'available' => $wallet->available_balance,
            'pending' => $wallet->pending_balance,
            'total' => $wallet->available_balance + $wallet->pending_balance,
            'rial_per_point' => $rate,
            'rial_value' => $wallet->available_balance * $rate,
            'pending_rial_value' => $wallet->pending_balance * $rate,
            'lifetime_earned' => $wallet->lifetime_earned,
            'lifetime_spent' => $wallet->lifetime_spent,
            'lifetime_expired' => $wallet->lifetime_expired,
            'next_release_at' => $nextRelease ? now()->parse($nextRelease)->toIso8601String() : null,
        ];
    }
}
