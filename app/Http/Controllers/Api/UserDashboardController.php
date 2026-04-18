<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\CustomerBookingSummaryResource;
use App\Http\Resources\DashboardResource;
use App\Services\UserDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    public function __construct(
        protected UserDashboardService $dashboardService
    ) {}

    public function dashboard(Request $request)
    {
        $payload = $this->dashboardService->getDashboardPayload($request->user());

        return ApiResponse::success(new DashboardResource($payload));
    }

    public function bookings(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
        $paginator = $this->dashboardService->paginateBookings($request->user(), $perPage);

        return ApiResponse::success([
            'bookings' => CustomerBookingSummaryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showBooking(Request $request, int $bookingId)
    {
        $booking = $this->dashboardService->findBookingForUser($request->user(), $bookingId);
        if (!$booking) {
            return ApiResponse::error('Booking not found', 404);
        }

        return ApiResponse::success(new BookingResource($booking));
    }
}
