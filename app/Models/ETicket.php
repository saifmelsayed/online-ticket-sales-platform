<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ETicket extends Model
{
    protected $fillable = [
        'booking_item_id',
        'qr_token',
        'file_path'
    ];
    
    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class);
    }
}
