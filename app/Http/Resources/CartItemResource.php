<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CartItem $item */
        $item = $this->resource;
        $item->loadMissing('tier.event');
        $tier = $item->tier;

        return [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'ticket_tier' => $tier ? [
                'id' => $tier->id,
                'name' => $tier->name,
                'base_price' => (string) $tier->base_price,
                'event' => $tier->relationLoaded('event') && $tier->event ? [
                    'id' => $tier->event->id,
                    'name' => $tier->event->name,
                    'event_datetime' => $tier->event->event_datetime,
                    'status' => $tier->event->status,
                ] : null,
            ] : null,
            'line_subtotal_credits' => $tier
                ? number_format((float) $tier->base_price * (int) $item->quantity, 2, '.', '')
                : '0.00',
        ];
    }
}
