<?php

namespace App\Filament\Admin\Concerns;

use App\Domain\Sponsor\SponsorModeration;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/** Approve / reject actions for sponsor-submitted objects (locations, campaigns, coupons). */
final class ModerationActions
{
    /** @return list<Action> */
    public static function make(callable $isPending): array
    {
        $allowed = fn () => auth('admin')->user()?->hasAbility('sponsors.manage') ?? false;

        return [
            Action::make('approve')->label('تأیید')->color('success')->icon('heroicon-o-check')
                ->visible(fn (Model $record) => $allowed() && $isPending($record))
                ->requiresConfirmation()
                ->action(function (Model $record) {
                    app(SponsorModeration::class)->approve($record, auth('admin')->user());
                    Notification::make()->title('تأیید شد.')->success()->send();
                }),
            Action::make('reject')->label('رد')->color('danger')->icon('heroicon-o-x-mark')
                ->visible(fn (Model $record) => $allowed() && $isPending($record))
                ->schema([Textarea::make('reason')->label('دلیل (به اسپانسر نمایش داده می‌شود)')->required()->maxLength(250)])
                ->action(function (Model $record, array $data) {
                    app(SponsorModeration::class)->reject($record, $data['reason'], auth('admin')->user());
                    Notification::make()->title('رد شد.')->warning()->send();
                }),
        ];
    }
}
