<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    protected $fillable = [
        'name', 'slug', 'city', 'address', 'latitude', 'longitude',
        'phone', 'timezone', 'description', 'active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'active' => 'boolean',
        ];
    }

    public function bookingSettings(): HasOne
    {
        return $this->hasOne(BookingSettings::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(BranchHour::class)->orderBy('weekday');
    }

    public function specialHours(): HasMany
    {
        return $this->hasMany(BranchSpecialHour::class)->orderBy('date');
    }
}
