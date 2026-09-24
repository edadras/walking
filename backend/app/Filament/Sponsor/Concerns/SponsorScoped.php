<?php

namespace App\Filament\Sponsor\Concerns;

use App\Models\SponsorUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant isolation for sponsor resources: every query is limited to the
 * signed-in user's sponsor and every ability comes from SponsorRole.
 * `$ability` gates the whole resource.
 */
trait SponsorScoped
{
    public static function sponsorUser(): ?SponsorUser
    {
        $user = auth('sponsor')->user();

        return $user instanceof SponsorUser ? $user : null;
    }

    public static function sponsorId(): int
    {
        return static::sponsorUser()?->sponsor_id ?? 0;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->where($query->getModel()->getTable().'.sponsor_id', static::sponsorId());
    }

    protected static function able(string $ability): bool
    {
        return static::sponsorUser()?->hasAbility($ability) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::able(static::$ability);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && $record->getAttribute('sponsor_id') === static::sponsorId();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
