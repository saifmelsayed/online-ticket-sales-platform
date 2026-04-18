<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogEventIndexRequest;
use App\Http\Resources\CatalogEventResource;
use App\Services\CatalogService;
use App\Support\ApiResponse;

class EventCatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService
    ) {}

    public function index(CatalogEventIndexRequest $request)
    {
        $paginator = $this->catalogService->listEvents($request);

        return ApiResponse::success([
            'events' => CatalogEventResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(int $id)
    {
        try {
            $event = $this->catalogService->getEvent($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Event not found', 404);
        }

        return ApiResponse::success(new CatalogEventResource($event));
    }
}
