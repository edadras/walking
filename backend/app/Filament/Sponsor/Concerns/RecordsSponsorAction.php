<?php

namespace App\Filament\Sponsor\Concerns;

use App\Domain\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

final class RecordsSponsorAction
{
    /** Status change by sponsor staff, audited with the staff member as actor. */
    public static function transition(Model $record, array $changes, string $action): void
    {
        $old = array_intersect_key($record->getAttributes(), $changes);
        $record->forceFill($changes)->save();
        app(AuditLogger::class)->log($action, $record, $old, array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $changes), actor: auth('sponsor')->user());
    }
}
