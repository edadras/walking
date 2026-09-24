<?php

namespace App\Domain\Audit;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Ip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?array $meta = null,
        ?Model $actor = null,
    ): AuditLog {
        $actor ??= $this->currentActor();

        return AuditLog::query()->create([
            'actor_type' => $this->actorType($actor),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'meta' => $meta,
            'ip_hash' => Ip::hash(Request::ip()),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255) ?: null,
        ]);
    }

    private function currentActor(): ?Model
    {
        foreach (['admin', 'sponsor', 'sanctum'] as $guard) {
            if (config("auth.guards.$guard") === null) {
                continue;
            }
            $user = Auth::guard($guard)->user();
            if ($user instanceof Model) {
                return $user;
            }
        }

        return null;
    }

    private function actorType(?Model $actor): string
    {
        return match (true) {
            $actor instanceof Admin => 'admin',
            $actor instanceof User => 'user',
            $actor === null => 'system',
            default => $actor->getMorphClass(),
        };
    }
}
