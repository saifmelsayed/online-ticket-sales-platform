<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketTier extends Model
{
    protected $fillable = ['event_id', 'name', 'base_price', 'total_seats', 'sold_count', 'sale_starts_at', 'sale_ends_at', 'version'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
    public function reservations(){
        return $this->hasMany(SeatReservation::class);
    }
    public function bookingItems(){
        return $this->hasMany(BookingItem::class);
    }
}
