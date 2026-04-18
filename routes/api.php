<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ETicketController;
use App\Http\Controllers\Api\EventCatalogController;
use App\Http\Controllers\Api\UserDashboardController;
use App\Http\Controllers\Api\OrganizerEventController;
use Illuminate\Support\Facades\Route;

Route::get('/events', [EventCatalogController::class, 'index']);
Route::get('/events/{id}', [EventCatalogController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register-organizer', [AuthController::class, 'registerOrganizer']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'customer'])->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::put('/items', [CartController::class, 'syncItem']);
});

Route::middleware(['auth:sanctum', 'customer', 'throttle:30,1'])->post('/checkout', [CheckoutController::class, 'store']);

Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'dashboard']);
    Route::get('/bookings', [UserDashboardController::class, 'bookings']);
    Route::get('/bookings/{bookingId}', [UserDashboardController::class, 'showBooking']);
    Route::get('/e-tickets/{eTicketId}/download', [ETicketController::class, 'download']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/overview', [AdminController::class, 'systemOverview']);
    Route::patch('/events/{eventId}/cancel', [AdminController::class, 'cancelEvent'])
        ->middleware('throttle:20,1');
    Route::get('/pending-organizers', [AdminController::class, 'pendingOrganizers']);
    Route::get('/approved-organizers', [AdminController::class, 'approvedOrganizers']);
    Route::patch('/approve/{organizerId}', [AdminController::class, 'approveOrganizer']);
    Route::patch('/reject/{organizerId}', [AdminController::class, 'rejectOrganizer']);
    Route::patch('/deactivate/{userId}', [AdminController::class, 'deactivateUser']);
    Route::patch('/reactivate/{userId}', [AdminController::class, 'reactivateUser']);
});

Route::middleware(['auth:sanctum', 'organizer'])->prefix('organizer/events')->group(function () {
    Route::post('/', [OrganizerEventController::class, 'store']);
    Route::get('/', [OrganizerEventController::class, 'index']);
    Route::put('/{eventId}', [OrganizerEventController::class, 'update']);
    Route::post('/{eventId}/tiers', [OrganizerEventController::class, 'addTier']);
    Route::put('/{eventId}/tiers/{tierId}', [OrganizerEventController::class, 'updateTier']);
    Route::delete('/{eventId}/tiers/{tierId}', [OrganizerEventController::class, 'destroyTier']);
    Route::patch('/{eventId}/cancel', [OrganizerEventController::class, 'cancel']);
});
