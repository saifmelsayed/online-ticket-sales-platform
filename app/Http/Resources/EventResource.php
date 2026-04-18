<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizer_id' => $this->organizer_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'event_datetime' => $this->event_datetime,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'is_online' => (bool) $this->is_online,
            'banner_image' => $this->banner_image,
            'status' => $this->status,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'organizer' => $this->whenLoaded('organizer', fn () => new UserResource($this->organizer)),
            'tiers' => TicketTierResource::collection($this->whenLoaded('tiers')),
        ];
    }
}
