<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'reference',
        'visit_date',
        'start_minute',
        'end_minute',
        'dining_table_id',
        'guests',
        'occasion',
        'first_name',
        'last_name',
        'phone',
        'birth_day',
        'birth_month',
        'birth_year',
        'source',
        'marketing_consent',
        'consent_at',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date:Y-m-d',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
            'guests' => 'integer',
            'birth_day' => 'integer',
            'birth_month' => 'integer',
            'birth_year' => 'integer',
            'marketing_consent' => 'boolean',
            'consent_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }
}
