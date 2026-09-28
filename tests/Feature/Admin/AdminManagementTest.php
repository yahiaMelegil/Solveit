<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\Admin\AdminInvitationNotification;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_super_admin_can_create_a_sub_admin_invitation(): void
    {
        Notification::fake();
        $actor = $this->superAdmin();
        $role = Role::findByName(AdminRole::SupportAdmin->value, 'admin');

        $response = $this->withToken($this->tokenFor($actor))
            ->postJson('/api/admin/admins', [
                'name' => '  Support Manager  ',
                'email' => '  SUPPORT@example.com ',
                'role_id' => $role->id,
            ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.admin.name', 'Support Manager')
            ->assertJsonPath('data.admin.email', 'support@example.com')
            ->assertJsonPath('data.admin.is_active', false)
            ->assertJsonPath('data.admin.status', 'pending_invitation');

        $admin = Admin::query()->findOrFail($response->json('data.admin.id'));

        $this->assertTrue($admin->hasRole(AdminRole::SupportAdmin->value));
        $this->assertNull($admin->invitation_accepted_at);
        $this->assertDatabaseHas('admin_invitations', [
            'admin_id' => $admin->id,
            'accepted_at' => null,
        ]);
        Notification::assertSentTo($admin, AdminInvitationNotification::class);
    }

    public function test_sub_admin_creation_rejects_the_super_admin_role(): void
    {
        Notification::fake();
        $actor = $this->superAdmin();
        $role = Role::findByName(AdminRole::SuperAdmin->value, 'admin');

        $this->withToken($this->tokenFor($actor))
            ->postJson('/api/admin/admins', [
                'name' => 'Another Super Admin',
                'email' => 'another-super@example.com',
                'role_id' => $role->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');

        $this->assertDatabaseMissing('admins', ['email' => 'another-super@example.com']);
    }

    public function test_invited_admin_can_accept_the_invitation_and_then_login(): void
    {
        Notification::fake();
        $actor = $this->superAdmin();
        $role = Role::findByName(AdminRole::SupportAdmin->value, 'admin');

        $response = $this->withToken($this->tokenFor($actor))
            ->postJson('/api/admin/admins', [
                'name' => 'Support Manager',
                'email' => 'support@example.com',
                'role_id' => $role->id,
            ])
            ->assertCreated();

        $admin = Admin::query()->findOrFail($response->json('data.admin.id'));
        $token = null;

        Notification::assertSentTo(
            $admin,
            AdminInvitationNotification::class,
            function (AdminInvitationNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->postJson('/api/admin/auth/invitations/accept', [
            'email' => 'SUPPORT@example.com',
            'token' => $token,
            'password' => 'NewSecurePassword1!',
            'password_confirmation' => 'NewSecurePassword1!',
        ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->invitation_accepted_at);
        $this->assertTrue(Hash::check('NewSecurePassword1!', $admin->password));

        $this->postJson('/api/admin/auth/login', [
            'email' => 'support@example.com',
            'password' => 'NewSecurePassword1!',
        ])
            ->assertOk()
            ->assertJsonPath('data.admin.status', 'active');

        $this->postJson('/api/admin/auth/invitations/accept', [
            'email' => 'support@example.com',
            'token' => $token,
            'password' => 'AnotherSecurePassword1!',
            'password_confirmation' => 'AnotherSecurePassword1!',
        ])->assertUnprocessable();
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $admin = Admin::factory()->pendingInvitation()->create();
        $plainTextToken = str_repeat('a', 64);
        $admin->invitation()->create([
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/admin/auth/invitations/accept', [
            'email' => $admin->email,
            'token' => $plainTextToken,
            'password' => 'NewSecurePassword1!',
            'password_confirmation' => 'NewSecurePassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->assertFalse($admin->fresh()->is_active);
    }

    public function test_pending_admin_cannot_login_or_use_an_existing_token(): void
    {
        $admin = Admin::factory()->pendingInvitation()->create([
            'password' => Hash::make('NewSecurePassword1!'),
        ]);

        $this->postJson('/api/admin/auth/login', [
            'email' => $admin->email,
            'password' => 'NewSecurePassword1!',
        ])->assertUnauthorized();

        $admin->forceFill(['is_active' => true])->save();
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertForbidden();
    }

    public function test_super_admin_can_edit_an_administrator(): void
    {
        $actor = $this->superAdmin();
        $target = Admin::factory()->create();
        $targetToken = $this->tokenFor($target);
        $targetTokenId = PersonalAccessToken::findToken($targetToken)->getKey();

        $this->withToken($this->tokenFor($actor))
            ->patchJson("/api/admin/admins/{$target->id}", [
                'name' => ' Updated Name ',
                'email' => ' UPDATED@example.com ',
            ])
            ->assertOk()
            ->assertJsonPath('data.admin.name', 'Updated Name')
            ->assertJsonPath('data.admin.email', 'updated@example.com');

        $this->assertDatabaseHas('admins', [
            'id' => $target->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $targetTokenId]);
    }

    public function test_changing_a_pending_admin_email_rotates_and_resends_the_invitation(): void
    {
        Notification::fake();
        $actor = $this->superAdmin();
        $target = Admin::factory()->pendingInvitation()->create();
        $target->invitation()->create([
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'expires_at' => now()->addHour(),
        ]);
        $oldHash = $target->invitation->token_hash;

        $this->withToken($this->tokenFor($actor))
            ->patchJson("/api/admin/admins/{$target->id}", [
                'email' => 'new-admin@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.admin.email', 'new-admin@example.com');

        $this->assertNotSame($oldHash, $target->invitation()->firstOrFail()->token_hash);
        Notification::assertSentTo($target, AdminInvitationNotification::class);
    }

    public function test_super_admin_can_rotate_and_resend_a_pending_invitation(): void
    {
        Notification::fake();
        $actor = $this->superAdmin();
        $target = Admin::factory()->pendingInvitation()->create();
        $target->invitation()->create([
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'expires_at' => now()->addHour(),
        ]);
        $oldHash = $target->invitation->token_hash;

        $this->withToken($this->tokenFor($actor))
            ->postJson("/api/admin/admins/{$target->id}/invitation")
            ->assertOk()
            ->assertJsonPath('data.admin.status', 'pending_invitation');

        $this->assertNotSame($oldHash, $target->invitation()->firstOrFail()->token_hash);
        Notification::assertSentTo($target, AdminInvitationNotification::class);
    }

    public function test_deactivating_an_admin_revokes_only_that_admins_tokens(): void
    {
        $actor = $this->superAdmin();
        $target = Admin::factory()->create();
        $actorToken = $this->tokenFor($actor);
        $targetToken = $this->tokenFor($target);
        $actorTokenId = PersonalAccessToken::findToken($actorToken)->getKey();
        $targetTokenId = PersonalAccessToken::findToken($targetToken)->getKey();

        $this->withToken($actorToken)
            ->patchJson("/api/admin/admins/{$target->id}/status", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.admin.status', 'inactive');

        $this->assertFalse($target->fresh()->is_active);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $targetTokenId]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $actorTokenId]);
    }

    public function test_super_admin_can_reactivate_an_accepted_admin(): void
    {
        $actor = $this->superAdmin();
        $target = Admin::factory()->inactive()->create();

        $this->withToken($this->tokenFor($actor))
            ->patchJson("/api/admin/admins/{$target->id}/status", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.admin.status', 'active');

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_itself_or_change_a_pending_invitation_status(): void
    {
        $actor = $this->superAdmin();
        $token = $this->tokenFor($actor);

        $this->withToken($token)
            ->patchJson("/api/admin/admins/{$actor->id}/status", [
                'is_active' => false,
            ])->assertConflict();

        $pending = Admin::factory()->pendingInvitation()->create();

        $this->withToken($token)
            ->patchJson("/api/admin/admins/{$pending->id}/status", [
                'is_active' => true,
            ])->assertConflict();
    }

    public function test_regular_users_and_unauthorized_admins_cannot_create_sub_admins(): void
    {
        $role = Role::findByName(AdminRole::SupportAdmin->value, 'admin');
        $payload = [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized@example.com',
            'role_id' => $role->id,
        ];

        $userToken = User::factory()->create()->createToken('User')->plainTextToken;
        $this->withToken($userToken)
            ->postJson('/api/admin/admins', $payload)
            ->assertForbidden();

        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::KycReviewer->value);
        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/admin/admins', $payload)
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_modify_a_super_admin_even_with_update_permission(): void
    {
        $role = Role::create(['name' => 'admin_editor', 'guard_name' => 'admin']);
        $role->givePermissionTo(AdminPermission::AdminsUpdate->value);
        $actor = Admin::factory()->create();
        $actor->assignRole($role);
        $target = $this->superAdmin();

        $this->withToken($this->tokenFor($actor))
            ->patchJson("/api/admin/admins/{$target->id}", ['name' => 'Changed'])
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
