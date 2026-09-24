<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['category', 'question', 'answer', 'sort', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort' => 'integer'];
    }
}
