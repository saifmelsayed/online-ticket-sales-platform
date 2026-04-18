<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterOrganizerRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request)
    {
        $user = $this->authService->registerCustomer(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password')
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Registered successfully', 201);
    }

    public function registerOrganizer(RegisterOrganizerRequest $request)
    {
        $this->authService->registerOrganizer(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password')
        );

        return ApiResponse::success(
            null,
            'Organizer registered and awaiting admin approval',
            201
        );
    }

    public function login(LoginRequest $request)
    {
        $user = $this->authService->login(
            $request->validated('email'),
            $request->validated('password')
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me(Request $request)
    {
        return ApiResponse::success(new UserResource($request->user()));
    }
}
