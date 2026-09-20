<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSettings extends Model
{
    protected $table = 'booking_settings';

    protected $fillable = ['branch_id', 'capacity', 'max_party_size', 'duration_minutes', 'buffer_minutes', 'active'];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'capacity' => 'integer',
            'max_party_size' => 'integer',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
