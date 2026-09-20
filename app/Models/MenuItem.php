<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = ['branch_id', 'name', 'name_en', 'description', 'description_en', 'category', 'price', 'active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'price' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
