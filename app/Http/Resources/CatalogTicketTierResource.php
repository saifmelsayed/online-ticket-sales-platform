<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogTicketTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->relationLoaded('reservations')) {
            $activeReserved = $this->reservations
                ->where('status', 'pending')
                ->filter(fn ($r) => $r->expires_at > now())
                ->sum('quantity');
        } else {
            $activeReserved = $this->reservations()
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->sum('quantity');
        }

        $remaining = max(0, (int) $this->total_seats - (int) $this->sold_count - (int) $activeReserved);

        $eventOk = $this->relationLoaded('event')
            && $this->event
            && $this->event->status === 'upcoming'
            && $this->event->event_datetime > now();

        $inSaleWindow = now()->between($this->sale_starts_at, $this->sale_ends_at);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'base_price' => $this->base_price,
            'final_price' => round((float) $this->base_price * 1.01, 2),
            'total_seats' => $this->total_seats,
            'sold_count' => $this->sold_count,
            'sale_starts_at' => $this->sale_starts_at,
            'sale_ends_at' => $this->sale_ends_at,
            'remaining_capacity' => $remaining,
            'is_available_for_purchase' => $remaining > 0 && $eventOk && $inSaleWindow,
        ];
    }
}
