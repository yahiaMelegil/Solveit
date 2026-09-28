<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_guest_cannot_access_admin_management_routes(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_admin_management_routes(): void
    {
        $token = User::factory()->create()->createToken('User')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $admin = Admin::factory()->create();

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_user_manager_can_browse_users(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::UserManager->value);
        User::factory()->create();

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_kyc_reviewer_cannot_browse_administrators(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::KycReviewer->value);

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/admin/admins')
            ->assertForbidden();
    }

    public function test_super_admin_bypasses_individual_permission_checks(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::SuperAdmin->value);

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/admin/permissions')
            ->assertOk();
    }

    public function test_authorized_admin_can_assign_a_role(): void
    {
        $actor = Admin::factory()->create();
        $target = Admin::factory()->create();
        $actor->assignRole(AdminRole::SuperAdmin->value);
        $role = Role::findByName(
            AdminRole::SupportAdmin->value,
            'admin',
        );

        $this->withToken($this->tokenFor($actor))
            ->postJson("/api/admin/admins/{$target->id}/roles/{$role->id}")
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole(AdminRole::SupportAdmin->value));
    }

    public function test_non_super_admin_cannot_assign_the_super_admin_role(): void
    {
        $role = Role::create(['name' => 'role_manager', 'guard_name' => 'admin']);
        $role->givePermissionTo(AdminPermission::AdminsAssignRoles->value);
        $actor = Admin::factory()->create();
        $actor->assignRole($role);
        $target = Admin::factory()->create();
        $superAdminRole = Role::findByName(AdminRole::SuperAdmin->value, 'admin');

        $this->withToken($this->tokenFor($actor))
            ->postJson("/api/admin/admins/{$target->id}/roles/{$superAdminRole->id}")
            ->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole(AdminRole::SuperAdmin->value));
    }

    public function test_non_super_admin_cannot_manage_an_existing_super_admin_roles(): void
    {
        $role = Role::create(['name' => 'role_manager', 'guard_name' => 'admin']);
        $role->givePermissionTo(AdminPermission::AdminsAssignRoles->value);
        $actor = Admin::factory()->create();
        $actor->assignRole($role);
        $target = $this->superAdmin();
        $supportRole = Role::findByName(AdminRole::SupportAdmin->value, 'admin');

        $this->withToken($this->tokenFor($actor))
            ->postJson("/api/admin/admins/{$target->id}/roles/{$supportRole->id}")
            ->assertForbidden();
    }

    public function test_super_admin_can_create_show_update_and_delete_a_role(): void
    {
        $admin = $this->superAdmin();

        $createResponse = $this->withToken($this->tokenFor($admin))
            ->postJson('/api/admin/roles', [
                'name' => 'content_manager',
                'permissions' => [
                    AdminPermission::UsersViewAny->value,
                    AdminPermission::UsersView->value,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.role.name', 'content_manager');

        $roleId = $createResponse->json('data.role.id');

        $this->withToken($this->tokenFor($admin))
            ->getJson("/api/admin/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('data.role.name', 'content_manager');

        $this->withToken($this->tokenFor($admin))
            ->putJson("/api/admin/roles/{$roleId}", [
                'name' => 'content_editor',
                'permissions' => [AdminPermission::UsersView->value],
            ])
            ->assertOk()
            ->assertJsonPath('data.role.name', 'content_editor')
            ->assertJsonPath('data.role.permissions.0', AdminPermission::UsersView->value);

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/admin/roles/{$roleId}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_role_creation_rejects_unknown_permissions(): void
    {
        $admin = $this->superAdmin();

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/admin/roles', [
                'name' => 'invalid_role',
                'permissions' => ['permission.doesNotExist'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_admin_without_role_create_permission_cannot_create_a_role(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::UserManager->value);

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/admin/roles', [
                'name' => 'forbidden_role',
                'permissions' => ['permission.doesNotExist'],
            ])
            ->assertForbidden();
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $target = Admin::factory()->create();
        $role = Role::findByName(AdminRole::SupportAdmin->value, 'admin');
        $target->assignRole($role);

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/admin/roles/{$role->id}")
            ->assertConflict();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_super_admin_role_cannot_be_renamed_or_deleted(): void
    {
        $admin = $this->superAdmin();
        $role = Role::findByName(AdminRole::SuperAdmin->value, 'admin');

        $this->withToken($this->tokenFor($admin))
            ->putJson("/api/admin/roles/{$role->id}", [
                'name' => 'renamed_super_admin',
                'permissions' => [AdminPermission::RolesViewAny->value],
            ])
            ->assertConflict();

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/admin/roles/{$role->id}")
            ->assertConflict();
    }

    public function test_custom_permissions_cannot_modify_or_delete_the_super_admin_role(): void
    {
        $roleManager = Role::create([
            'name' => 'system_role_editor',
            'guard_name' => 'admin',
        ]);
        $roleManager->givePermissionTo([
            AdminPermission::RolesUpdate->value,
            AdminPermission::RolesDelete->value,
        ]);
        $actor = Admin::factory()->create();
        $actor->assignRole($roleManager);
        $superAdminRole = Role::findByName(AdminRole::SuperAdmin->value, 'admin');

        $this->withToken($this->tokenFor($actor))
            ->putJson("/api/admin/roles/{$superAdminRole->id}", [
                'name' => AdminRole::SuperAdmin->value,
                'permissions' => [AdminPermission::UsersView->value],
            ])
            ->assertForbidden();

        $this->withToken($this->tokenFor($actor))
            ->deleteJson("/api/admin/roles/{$superAdminRole->id}")
            ->assertForbidden();
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::SuperAdmin->value);

        return $admin;
    }

    private function tokenFor(Admin $admin): string
    {
        return $admin->createToken('Test', [Admin::ACCESS_ABILITY])->plainTextToken;
    }
}
