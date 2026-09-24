<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasPublicId, SoftDeletes;

    protected $guarded = ['id', 'public_id', 'user_id'];

    protected $hidden = ['id', 'user_id'];

    protected $attributes = ['is_default' => false];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return array<string, string|null> */
    public function snapshot(): array
    {
        return $this->only(['title', 'recipient', 'phone', 'province', 'city', 'line', 'postal_code']);
    }
}
