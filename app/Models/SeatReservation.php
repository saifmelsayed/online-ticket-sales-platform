<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatReservation extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_tier_id',
        'quantity',
        'reserved_at',
        'expires_at',
        'status'
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function tier()
    {
        return $this->belongsTo(TicketTier::class , 'ticket_tier_id');
    }
}
