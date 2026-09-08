<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    protected $fillable = ['name', 'capacity', 'x', 'y', 'active'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'x' => 'integer',
            'y' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
