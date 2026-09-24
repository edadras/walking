<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

/**
 * Role-based access for admin resources. `$viewAbility` gates reading,
 * `$manageAbility` gates create/edit/delete. Both map to AdminRole::abilities().
 */
trait RequiresAbility
{
    protected static function admin(): ?Admin
    {
        $user = auth('admin')->user();

        return $user instanceof Admin ? $user : null;
    }

    protected static function allows(?string $ability): bool
    {
        return $ability !== null && (static::admin()?->hasAbility($ability) ?? false);
    }

    public static function canViewAny(): bool
    {
        return static::allows(static::$viewAbility ?? null);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::allows(static::$manageAbility ?? null);
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows(static::$manageAbility ?? null);
    }

    public static function canDelete(Model $record): bool
    {
        return static::allows(static::$manageAbility ?? null);
    }

    public static function canDeleteAny(): bool
    {
        return static::allows(static::$manageAbility ?? null);
    }
}
