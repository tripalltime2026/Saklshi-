<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchHour extends Model
{
    protected $fillable = ['branch_id', 'weekday', 'opens_at', 'closes_at', 'closed'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'closed' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
