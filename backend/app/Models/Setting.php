<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'description', 'is_public', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_public' => 'boolean',
        ];
    }
}
