<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'total_credits',
        'status',
        'idempotency_key'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
    
    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }
}
