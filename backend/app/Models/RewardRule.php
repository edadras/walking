<?php

namespace App\Models;

use App\Enums\RewardRuleType;
use Illuminate\Database\Eloquent\Model;

class RewardRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rule_type' => RewardRuleType::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'steps' => 'integer',
            'points' => 'integer',
            'multiplier' => 'float',
            'cap' => 'integer',
            'priority' => 'integer',
            'params' => 'json',
        ];
    }

    /** @return list<int> Carbon dayOfWeek values (0 = Sunday … 5 = Friday, 6 = Saturday) */
    public function days(): array
    {
        return $this->days_of_week === null || $this->days_of_week === '' ? [] : array_map('intval', explode(',', $this->days_of_week));
    }
}
