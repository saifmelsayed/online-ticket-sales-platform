<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\SyncCartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function show(Request $request)
    {
        $cart = $this->cartService->getCartForUser($request->user());

        return ApiResponse::success(new CartResource($cart));
    }

    public function syncItem(SyncCartItemRequest $request)
    {
        $result = $this->cartService->syncCartItem(
            $request->user(),
            $request->integer('ticket_tier_id'),
            $request->integer('quantity')
        );

        $result['cart']->loadMissing(['items.tier.event']);

        $payload = [
            'cart' => new CartResource($result['cart']),
            'reservation' => $result['reservation'] ? [
                'id' => $result['reservation']->id,
                'ticket_tier_id' => $result['reservation']->ticket_tier_id,
                'quantity' => $result['reservation']->quantity,
                'expires_at' => $result['reservation']->expires_at,
                'status' => $result['reservation']->status,
            ] : null,
        ];

        return ApiResponse::success($payload, 'Cart updated');
    }
}
