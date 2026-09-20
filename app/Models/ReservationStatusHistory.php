<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReservationStatusHistory extends Model
{
    protected $table = 'reservation_status_history';
    public $timestamps = false;
    protected function casts(): array { return ['created_at' => 'datetime']; }
}
