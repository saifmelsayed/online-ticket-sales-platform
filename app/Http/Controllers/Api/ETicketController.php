<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ETicketController extends Controller
{
    public function __construct(
        protected UserDashboardService $dashboardService
    ) {}

    public function download(Request $request, int $eTicketId): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $ticket = $this->dashboardService->findETicketForUser($request->user(), $eTicketId);
        if (!$ticket) {
            return ApiResponse::error('E-ticket not found', 404);
        }

        $body = $this->dashboardService->buildEticketPlainText($ticket);
        $filename = 'e-ticket-'.$ticket->id.'.txt';

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
