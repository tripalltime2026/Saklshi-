<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSlot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reservation_id',
        'dining_table_id',
        'visit_date',
        'minute',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date:Y-m-d',
            'minute' => 'integer',
        ];
    }
}
