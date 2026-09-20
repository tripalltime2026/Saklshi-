<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSpecialHour extends Model
{
    protected $fillable = ['branch_id', 'date', 'opens_at', 'closes_at', 'closed', 'capacity_override', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'closed' => 'boolean', 'capacity_override' => 'integer'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
