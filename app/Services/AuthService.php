<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function registerCustomer(string $name, string $email, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'customer',
            'credit_balance' => 2000,
            'is_active' => true,
        ]);
    }

    public function registerOrganizer(string $name, string $email, string $password): void
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);

        Organizer::create([
            'user_id' => $user->id,
            'approval_status' => 'pending',
        ]);
    }

    public function login(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->role === 'organizer') {
            $organizer = $user->organizer;
            if (!$organizer || $organizer->approval_status !== 'approved') {
                throw ValidationException::withMessages([
                    'email' => ['Organizer account is pending admin approval.'],
                ]);
            }
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account is deactivated.'],
            ]);
        }

        return $user;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
