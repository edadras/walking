<?php

namespace App\Filament\Admin\Resources\Cashout;

/** Shared labels/colours for the pending → verified/rejected review states. */
final class StatusBadge
{
    public const REVIEW = ['pending' => 'در انتظار بررسی', 'verified' => 'تأیید شده', 'rejected' => 'رد شده'];

    public static function color(string $state): string
    {
        return match ($state) {
            'pending', 'approved' => 'warning',
            'verified', 'paid' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }
}
