<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/** Internal auto-increment id + public ULID used in URLs and API payloads. */
trait HasPublicId
{
    use HasUlids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
