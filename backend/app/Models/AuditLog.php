<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only audit trail. Updates and deletes are refused at model level; in
 * production the application DB user additionally only has INSERT/SELECT on it.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id',
        'old_values', 'new_values', 'meta', 'ip_hash', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'json',
            'new_values' => 'json',
            'meta' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit logs are immutable.'));
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
