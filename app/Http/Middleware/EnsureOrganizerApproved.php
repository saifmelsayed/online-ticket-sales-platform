<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'organizer' || !$user->is_active) {
            return ApiResponse::error('Organizer access only.', 403);
        }

        $organizer = $user->organizer;
        if (!$organizer || $organizer->approval_status !== 'approved') {
            return ApiResponse::error('Organizer account is not approved.', 403);
        }

        return $next($request);
    }
}
