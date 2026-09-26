<?php

namespace Tests\Feature\Authentication;

use App\Models\Admin;
use App\Models\Expert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_user_registers_with_a_verification_only_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Abdullah Abu Shamla',
            'email' => 'Abdullah@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'Web App',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Registration completed successfully. Please verify your email address.')
            ->assertJsonPath('data.user.name', 'Abdullah Abu Shamla')
            ->assertJsonPath('data.user.email', 'abdullah@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.token_scope', 'email_verification')
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                    'token',
                    'token_type',
                    'token_scope',
                    'email_verified',
                ],
            ])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $user = User::query()->where('email', 'abdullah@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertNotSame('password123', $user->password);
        $this->assertNotEmpty($response->json('data.token'));
        $token = PersonalAccessToken::findToken($response->json('data.token'));

        $this->assertNotNull($token);
        $this->assertTrue($token->can(User::VERIFICATION_ABILITY));
        $this->assertFalse($token->can(User::ACCESS_ABILITY));
        $this->assertFalse($token->can(Admin::ACCESS_ABILITY));
        $this->assertFalse($token->can(Expert::ACCESS_ABILITY));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'Web App',
        ]);
    }

    public function test_registration_returns_consistent_validation_errors(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'status' => false,
                'message' => 'The provided data is invalid.',
                'errors' => [
                    'name' => ['The name field is required.'],
                    'email' => ['The email field must be a valid email address.'],
                    'password' => [
                        'The password field must be at least 8 characters.',
                        'The password field confirmation does not match.',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'abdullah@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Another User',
            'email' => 'ABDULLAH@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'abdullah@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'Abdullah@Example.com',
            'password' => 'password123',
            'device_name' => 'Web App',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Authentication completed successfully.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.token_scope', 'full_access')
            ->assertJsonPath('data.email_verified', true);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Web App',
        ]);
    }

    public function test_login_rejects_an_incorrect_password_without_revealing_account_details(): void
    {
        User::factory()->create([
            'email' => 'abdullah@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'abdullah@example.com',
            'password' => 'incorrect-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'The provided credentials are incorrect.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_unverified_user_cannot_receive_normal_authenticated_access(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'Web App',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonPath('email_verified', false)
            ->assertJsonPath('data.token_scope', 'email_verification');

        $token = PersonalAccessToken::findToken($response->json('data.token'));

        $this->assertNotNull($token);
        $this->assertTrue($token->can(User::VERIFICATION_ABILITY));
        $this->assertFalse($token->can(User::ACCESS_ABILITY));

        $this->app['auth']->forgetGuards();

        $this->withToken($response->json('data.token'))
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_an_unverified_user_is_blocked_even_with_a_previously_issued_access_token(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('Legacy Token', [User::ACCESS_ABILITY])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Your email address has not been verified.',
                'email_verified' => false,
            ]);
    }

    public function test_an_authenticated_user_can_be_retrieved_with_a_bearer_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('Test Client')->plainTextToken;

        $this->withToken($plainTextToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');
    }

    public function test_a_protected_endpoint_rejects_a_request_without_a_token(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_administrator_and_expert_tokens_cannot_access_regular_user_routes(): void
    {
        $adminToken = Admin::factory()->create()
            ->createToken('Admin', [Admin::ACCESS_ABILITY])
            ->plainTextToken;
        $expertToken = Expert::factory()->create()
            ->createToken('Expert', [Expert::ACCESS_ABILITY])
            ->plainTextToken;

        foreach ([$adminToken, $expertToken] as $token) {
            $this->app['auth']->forgetGuards();

            $this->withToken($token)
                ->getJson('/api/user')
                ->assertForbidden()
                ->assertExactJson([
                    'status' => false,
                    'message' => 'You are not authorized to access the user area.',
                ]);
        }
    }

    public function test_a_regular_user_token_without_the_required_ability_is_rejected(): void
    {
        $token = User::factory()->create()
            ->createToken('Limited Client', [])
            ->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'message' => 'You are not authorized to access the user area.',
            ]);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('Current Device')->plainTextToken;
        $otherToken = $user->createToken('Other Device')->plainTextToken;
        $currentTokenId = PersonalAccessToken::findToken($currentToken)->getKey();
        $otherTokenId = PersonalAccessToken::findToken($otherToken)->getKey();

        $this->withToken($currentToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Logged out successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentTokenId]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherTokenId]);
    }

    public function test_a_revoked_token_can_no_longer_access_protected_routes(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('Web App')->plainTextToken;

        $this->withToken($plainTextToken)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Each real API call has a fresh guard. Reset the guard between the two
        // requests here so this test mirrors that request lifecycle.
        $this->app['auth']->forgetGuards();

        $this->withToken($plainTextToken)
            ->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_all_revokes_every_token_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $firstToken = $user->createToken('Web App')->plainTextToken;
        $user->createToken('Mobile App');

        $this->withToken($firstToken)
            ->postJson('/api/logout-all')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Logged out from all devices successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'unknown@example.com',
                'password' => 'password123',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ])
            ->assertTooManyRequests()
            ->assertExactJson([
                'status' => false,
                'message' => 'Too many attempts. Please try again later.',
            ]);
    }
}
