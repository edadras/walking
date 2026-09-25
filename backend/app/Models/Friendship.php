<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /** @return array{0: int, 1: int} */
    public static function pair(int $a, int $b): array
    {
        return [min($a, $b), max($a, $b)];
    }

    public function otherThan(int $userId): int
    {
        return $this->user_low_id === $userId ? $this->user_high_id : $this->user_low_id;
    }
}
