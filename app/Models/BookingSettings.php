<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSettings extends Model
{
    protected $table = 'booking_settings';
    protected $fillable = ['capacity', 'max_party_size', 'duration_minutes', 'buffer_minutes', 'active'];
    protected function casts(): array
    {
        return ['capacity' => 'integer', 'max_party_size' => 'integer', 'duration_minutes' => 'integer', 'buffer_minutes' => 'integer', 'active' => 'boolean'];
    }
}
