<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalRecord extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'local_date' => 'immutable_date', 'achieved_at' => 'datetime'];
    }
}
