<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\BookingResource;
use App\Services\CheckoutService;
use App\Support\ApiResponse;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    public function store(CheckoutRequest $request)
    {
        $booking = $this->checkoutService->checkout(
            $request->user(),
            $request->string('idempotency_key')->toString()
        );

        return ApiResponse::success(new BookingResource($booking), 'Checkout completed');
    }
}
