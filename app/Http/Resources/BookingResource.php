<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'total_credits' => (string) $this->total_credits,
            'status' => $this->status,
            'idempotency_key' => $this->idempotency_key,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'event_datetime' => $this->event->event_datetime,
            ]),
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
