<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreEventRequest;
use App\Http\Requests\Organizer\StoreTicketTierRequest;
use App\Http\Requests\Organizer\UpdateEventRequest;
use App\Http\Requests\Organizer\UpdateTicketTierRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\TicketTierResource;
use App\Services\OrganizerEventService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class OrganizerEventController extends Controller
{
    public function __construct(
        protected OrganizerEventService $organizerEventService
    ) {}

    public function store(StoreEventRequest $request)
    {
        $data = $request->validated();
        if ($request->hasFile('banner_image')) {
            $data['banner_image'] = $request->file('banner_image');
        }

        $event = $this->organizerEventService->createEvent($data, $request->user()->id);

        return ApiResponse::success(new EventResource($event), 'Event created', 201);
    }

    public function index(Request $request)
    {
        $events = $this->organizerEventService->listMyEvents($request->user()->id);

        return ApiResponse::success(EventResource::collection($events));
    }

    public function update(UpdateEventRequest $request, int $eventId)
    {
        $data = $request->validated();
        if ($request->hasFile('banner_image')) {
            $data['banner_image'] = $request->file('banner_image');
        }

        try {
            $event = $this->organizerEventService->updateEvent($eventId, $data, $request->user()->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Event not found', 404);
        }

        return ApiResponse::success(new EventResource($event), 'Event updated');
    }

    public function addTier(StoreTicketTierRequest $request, int $eventId)
    {
        try {
            $tier = $this->organizerEventService->addTier($eventId, $request->validated(), $request->user()->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Event not found', 404);
        }

        return ApiResponse::success(new TicketTierResource($tier), 'Tier created', 201);
    }

    public function updateTier(UpdateTicketTierRequest $request, int $eventId, int $tierId)
    {
        try {
            $tier = $this->organizerEventService->updateTier(
                $eventId,
                $tierId,
                $request->validated(),
                $request->user()->id
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Event or tier not found', 404);
        }

        return ApiResponse::success(new TicketTierResource($tier), 'Tier updated');
    }

    public function destroyTier(Request $request, int $eventId, int $tierId)
    {
        try {
            $this->organizerEventService->deleteTier($eventId, $tierId, $request->user()->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Event or tier not found', 404);
        }

        return ApiResponse::success(null, 'Tier deleted');
    }

    public function cancel(Request $request, int $eventId)
    {
        try {
            $event = $this->organizerEventService->cancelOwnEvent($eventId, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::success(
            new EventResource($event->load(['category', 'tiers', 'organizer'])),
            'Event cancelled and refunds processed'
        );
    }
}
