<?php

namespace App\Console\Commands;

use App\Models\CartItem;
use App\Models\SeatReservation;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire pending seat holds past their TTL and drop matching cart lines';

    public function handle(): int
    {
        $expired = SeatReservation::query()
            ->where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $reservation) {
            $reservation->update(['status' => 'expired']);

            CartItem::query()
                ->where('ticket_tier_id', $reservation->ticket_tier_id)
                ->whereHas('cart', fn ($q) => $q->where('user_id', $reservation->user_id))
                ->delete();
        }

        if ($expired->isNotEmpty()) {
            $this->info("Expired {$expired->count()} reservation(s).");
        }

        return self::SUCCESS;
    }
}
