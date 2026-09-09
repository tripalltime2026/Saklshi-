<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = ['name', 'name_en', 'description', 'description_en', 'category', 'price', 'active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'active' => 'boolean',
        ];
    }
}

