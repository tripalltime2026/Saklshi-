<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    protected $fillable = ['branch_id', 'name', 'capacity', 'x', 'y', 'active'];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'capacity' => 'integer',
            'x' => 'integer',
            'y' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
