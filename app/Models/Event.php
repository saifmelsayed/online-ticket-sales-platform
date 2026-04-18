<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organizer_id',
        'category_id',
        'name',
        'description',
        'event_datetime',
        'venue_name',
        'venue_address',
        'is_online',
        'banner_image',
        'status'
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tiers()
    {
        return $this->hasMany(TicketTier::class);
    }
    public function bookings(){
        return $this->hasMany(Booking::class);
    }
}
