<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @param  array{user: \App\Models\User, upcoming_bookings: \Illuminate\Support\Collection, recent_bookings: \Illuminate\Support\Collection}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $upcoming = $this->resource['upcoming_bookings'];
        $recent = $this->resource['recent_bookings'];

        return [
            'credit_balance' => (string) $user->credit_balance,
            'upcoming_bookings' => CustomerBookingSummaryResource::collection($upcoming),
            'recent_bookings' => CustomerBookingSummaryResource::collection($recent),
        ];
    }
}
