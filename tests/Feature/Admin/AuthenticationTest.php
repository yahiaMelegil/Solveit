<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_administrator_can_login_and_receives_a_restricted_token(): void
    {
        [$admin, $password] = $this->createAdminWithKnownPassword();

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => mb_strtoupper($admin->email),
            'password' => $password,
            'device_name' => 'Admin Dashboard',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Administrator logged in successfully.')
            ->assertJsonPath('data.admin.id', $admin->id)
            ->assertJsonPath('data.admin.email', $admin->email)
            ->assertJsonPath('data.admin.is_active', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'admin' => ['id', 'name', 'email', 'is_active', 'created_at', 'updated_at'],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonMissingPath('data.admin.password')
            ->assertJsonMissingPath('data.admin.remember_token');

        $token = PersonalAccessToken::findToken($response->json('data.token'));

        $this->assertNotNull($token);
        $this->assertTrue($token->can(Admin::ACCESS_ABILITY));
        $this->assertFalse($token->can('user:access'));
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'tokenable_type' => Admin::class,
            'name' => 'Admin Dashboard',
        ]);
    }

    public function test_administrator_login_returns_consistent_validation_errors(): void
    {
        $this->postJson('/api/admin/auth/login', [
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertExactJson([
                'status' => false,
                'message' => 'The provided data is invalid.',
                'errors' => [
                    'email' => ['The email field must be a valid email address.'],
                    'password' => ['The password field is required.'],
                ],
            ]);
    }

    public function test_administrator_login_rejects_an_unknown_email_generically(): void
    {
        $this->postJson('/api/admin/auth/login', [
            'email' => fake()->unique()->safeEmail(),
            'password' => Str::password(24),
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'The provided credentials are incorrect.',
            ]);
    }

    public function test_administrator_login_rejects_an_incorrect_password_generically(): void
    {
        [$admin] = $this->createAdminWithKnownPassword();

        $this->postJson('/api/admin/auth/login', [
            'email' => $admin->email,
            'password' => Str::password(24),
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'The provided credentials are incorrect.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_inactive_administrator_cannot_login(): void
    {
        [$admin, $password] = $this->createAdminWithKnownPassword(['is_active' => false]);

        $this->postJson('/api/admin/auth/login', [
            'email' => $admin->email,
            'password' => $password,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_administrator_can_access_the_me_endpoint_with_a_valid_bearer_token(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->createAdminToken($admin);

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Administrator retrieved successfully.')
            ->assertJsonPath('data.admin.id', $admin->id)
            ->assertJsonMissingPath('data.admin.password')
            ->assertJsonMissingPath('data.admin.remember_token');
    }

    public function test_admin_routes_reject_requests_without_a_token(): void
    {
        $this->getJson('/api/admin/auth/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_admin_routes_reject_an_invalid_token(): void
    {
        $this->withToken(Str::random(80))
            ->getJson('/api/admin/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_a_regular_user_token_cannot_access_administrator_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Web App')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'message' => 'You are not authorized to access the administration area.',
            ]);
    }

    public function test_an_administrator_token_without_the_required_ability_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('Limited Client', [])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'message' => 'You are not authorized to access the administration area.',
            ]);
    }

    public function test_an_inactive_administrator_cannot_use_a_previously_created_token(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->createAdminToken($admin);

        $admin->update(['is_active' => false]);

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'You are not authorized to access the administration area.',
            );
    }

    public function test_logout_revokes_only_the_current_administrator_token(): void
    {
        $admin = Admin::factory()->create();
        $currentToken = $this->createAdminToken($admin, 'Current Device');
        $otherToken = $this->createAdminToken($admin, 'Other Device');
        $currentTokenId = PersonalAccessToken::findToken($currentToken)->getKey();
        $otherTokenId = PersonalAccessToken::findToken($otherToken)->getKey();

        $this->withToken($currentToken)
            ->postJson('/api/admin/auth/logout')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Administrator logged out successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentTokenId]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherTokenId]);
    }

    public function test_a_revoked_administrator_token_cannot_be_reused(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->createAdminToken($admin);

        $this->withToken($token)->postJson('/api/admin/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_all_revokes_only_the_authenticated_administrators_tokens(): void
    {
        $admin = Admin::factory()->create();
        $otherAdmin = Admin::factory()->create();
        $currentToken = $this->createAdminToken($admin, 'Current Device');
        $this->createAdminToken($admin, 'Other Device');
        $otherAdminsToken = $this->createAdminToken($otherAdmin);
        $otherAdminsTokenId = PersonalAccessToken::findToken($otherAdminsToken)->getKey();

        $this->withToken($currentToken)
            ->postJson('/api/admin/auth/logout-all')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Administrator logged out from all devices successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherAdminsTokenId]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'tokenable_type' => Admin::class,
        ]);
    }

    public function test_administrator_login_is_rate_limited_by_email_and_ip_address(): void
    {
        $email = fake()->unique()->safeEmail();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/admin/auth/login', [
                'email' => $email,
                'password' => Str::password(24),
            ])->assertUnauthorized();
        }

        $this->postJson('/api/admin/auth/login', [
            'email' => mb_strtoupper($email),
            'password' => Str::password(24),
        ])
            ->assertTooManyRequests()
            ->assertExactJson([
                'status' => false,
                'message' => 'Too many attempts. Please try again later.',
            ]);
    }

    public function test_admin_seeder_creates_the_initial_administrator_from_configuration(): void
    {
        $password = Str::password(24);
        $email = fake()->unique()->safeEmail();

        config([
            'admin.initial.name' => fake()->name(),
            'admin.initial.email' => mb_strtoupper($email),
            'admin.initial.password' => $password,
        ]);

        $this->seed(AdminSeeder::class);

        $admin = Admin::query()->where('email', mb_strtolower($email))->firstOrFail();

        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->invitation_accepted_at);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertTrue($admin->hasRole(AdminRole::SuperAdmin->value));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{Admin, string}
     */
    private function createAdminWithKnownPassword(array $attributes = []): array
    {
        $password = Str::password(24);
        $admin = Admin::factory()->create([
            ...$attributes,
            'password' => Hash::make($password),
        ]);

        return [$admin, $password];
    }

    private function createAdminToken(Admin $admin, string $name = 'Admin Dashboard'): string
    {
        return $admin->createToken($name, [Admin::ACCESS_ABILITY])->plainTextToken;
    }
}
