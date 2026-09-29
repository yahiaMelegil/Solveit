<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DynamicAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_revoking_a_role_blocks_an_existing_bearer_token_on_its_next_request(): void
    {
        $manager = Admin::factory()->create();
        $manager->assignRole(AdminRole::UserManager->value);
        $managerToken = $manager->createToken('Existing admin session', [Admin::ACCESS_ABILITY])->plainTextToken;
        $superAdmin = Admin::factory()->create();
        $superAdmin->assignRole(AdminRole::SuperAdmin->value);
        $superToken = $superAdmin->createToken('Owner session', [Admin::ACCESS_ABILITY])->plainTextToken;
        $role = Role::findByName(AdminRole::UserManager->value, 'admin');

        $this->withToken($managerToken)->getJson('/api/admin/users')->assertOk();
        // Each real HTTP request resolves its bearer token with a fresh auth guard.
        $this->app['auth']->forgetGuards();

        $this->withToken($superToken)
            ->deleteJson("/api/admin/admins/{$manager->id}/roles/{$role->id}")
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($managerToken)->getJson('/api/admin/users')->assertForbidden();
        $this->withToken($managerToken)
            ->getJson('/api/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.admin.permissions', []);
    }

    public function test_inactive_admin_is_blocked_even_if_a_token_row_still_exists(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('Existing admin session', [Admin::ACCESS_ABILITY])->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/auth/me')->assertOk();
        $admin->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/admin/auth/me')->assertForbidden();
    }
}
