<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id',
        'ticket_tier_id',
        'quantity',
        'unit_price'
    ];
    
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
    
    public function tier()
    {
        return $this->belongsTo(TicketTier::class, 'ticket_tier_id');
    }
    
    public function eTicket()
    {
        return $this->hasOne(ETicket::class);
    }
    
}
