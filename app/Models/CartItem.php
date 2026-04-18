<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'ticket_tier_id', 'quantity'];
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
    public function tier()
    {
        return $this->belongsTo(TicketTier::class, 'ticket_tier_id');
    }
}
