<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrganizerResource;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function systemOverview()
    {
        return ApiResponse::success($this->adminService->systemOverview());
    }

    public function pendingOrganizers()
    {
        $list = $this->adminService->pendingOrganizers();

        return ApiResponse::success(OrganizerResource::collection($list));
    }

    public function approvedOrganizers()
    {
        $list = $this->adminService->approvedOrganizers();

        return ApiResponse::success(OrganizerResource::collection($list));
    }

    public function approveOrganizer(int $organizerId)
    {
        $this->adminService->approveOrganizer($organizerId);

        return ApiResponse::success(null, 'Organizer approved successfully');
    }

    public function rejectOrganizer(int $organizerId)
    {
        $this->adminService->rejectOrganizer($organizerId);

        return ApiResponse::success(null, 'Organizer rejected successfully');
    }

    public function deactivateUser(Request $request, int $userId)
    {
        try {
            $this->adminService->deactivateUser($userId, $request->user());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, 'User deactivated');
    }

    public function reactivateUser(int $userId)
    {
        $this->adminService->reactivateUser($userId);

        return ApiResponse::success(null, 'User reactivated');
    }

    public function cancelEvent(int $eventId)
    {
        try {
            $event = $this->adminService->cancelEventWithRefunds($eventId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Event not found', 404);
        }

        return ApiResponse::success(
            new EventResource($event->load(['category', 'organizer'])),
            'Event cancelled; refunds processed'
        );
    }
}
