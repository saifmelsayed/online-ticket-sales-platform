<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'admin'])->get('/__test/admin-only', fn () => response()->json(['success' => true, 'gate' => 'admin']));

        Route::middleware(['api', 'auth:sanctum', 'customer'])->get('/__test/customer-only', fn () => response()->json(['success' => true, 'gate' => 'customer']));

        Route::middleware(['api', 'auth:sanctum', 'organizer'])->get('/__test/organizer-only', fn () => response()->json(['success' => true, 'gate' => 'organizer']));
    }

    public function test_admin_middleware_allows_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/__test/admin-only')
            ->assertOk()
            ->assertJson(['success' => true, 'gate' => 'admin']);
    }

    public function test_admin_middleware_blocks_customer(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        Sanctum::actingAs($customer);

        $this->getJson('/__test/admin-only')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Admin access only.',
            ]);
    }

    public function test_customer_middleware_allows_customer(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        Sanctum::actingAs($customer);

        $this->getJson('/__test/customer-only')
            ->assertOk()
            ->assertJson(['gate' => 'customer']);
    }

    public function test_customer_middleware_blocks_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/__test/customer-only')
            ->assertForbidden()
            ->assertJson(['success' => false]);
    }

    public function test_organizer_middleware_allows_approved_organizer(): void
    {
        $user = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Organizer::create([
            'user_id' => $user->id,
            'approval_status' => 'approved',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/__test/organizer-only')
            ->assertOk()
            ->assertJson(['gate' => 'organizer']);
    }

    public function test_organizer_middleware_blocks_pending_organizer(): void
    {
        $user = User::factory()->create([
            'role' => 'organizer',
            'credit_balance' => 0,
            'is_active' => true,
        ]);
        Organizer::create([
            'user_id' => $user->id,
            'approval_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/__test/organizer-only')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Organizer account is not approved.',
            ]);
    }

    public function test_role_middleware_blocks_inactive_user(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'credit_balance' => 0,
            'is_active' => false,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/__test/admin-only')
            ->assertForbidden();
    }
}
