<?php

namespace App\Models;

use App\Support\PiiHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends Model
{
    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['national_code', 'national_code_hash'];

    protected function casts(): array
    {
        return ['national_code' => 'encrypted', 'birth_date' => 'date', 'reviewed_at' => 'datetime', 'submitted_at' => 'datetime', 'retain_until' => 'datetime'];
    }

    public static function hashNationalCode(string $code): string
    {
        return PiiHash::make('nid', $code);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
