<?php

namespace Tests\Feature\Expert\Auth;

use App\Enums\ExpertKycStatus;
use App\Models\Expert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unverified_expert_awaiting_kyc_can_login(): void
    {
        [$expert, $password] = $this->createExpertWithKnownPassword();

        $response = $this->postJson('/api/expert/auth/login', [
            'email' => mb_strtoupper($expert->email),
            'password' => $password,
            'device_name' => 'Expert Dashboard',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Expert logged in successfully.')
            ->assertJsonPath('data.expert.id', $expert->id)
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('data.kyc_status', ExpertKycStatus::NotSubmitted->value)
            ->assertJsonPath('data.token_type', 'Bearer');

        $token = PersonalAccessToken::findToken($response->json('data.token'));

        $this->assertNotNull($token);
        $this->assertTrue($token->can(Expert::ACCESS_ABILITY));
        $this->assertFalse($token->can('admin:access'));
    }

    public function test_a_verified_expert_can_login_without_becoming_kyc_approved(): void
    {
        [$expert, $password] = $this->createExpertWithKnownPassword([
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/expert/auth/login', [
            'email' => $expert->email,
            'password' => $password,
        ])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.kyc_status', ExpertKycStatus::NotSubmitted->value);
    }

    public function test_login_validation_failure_uses_the_standard_response(): void
    {
        $this->postJson('/api/expert/auth/login', [
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

    public function test_login_rejects_an_unknown_email_generically(): void
    {
        $this->postJson('/api/expert/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'The provided credentials are incorrect.',
            ]);
    }

    public function test_login_rejects_an_incorrect_password_generically(): void
    {
        $expert = Expert::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/expert/auth/login', [
            'email' => $expert->email,
            'password' => 'incorrect-password',
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'The provided credentials are incorrect.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_inactive_expert_cannot_login(): void
    {
        [$expert, $password] = $this->createExpertWithKnownPassword([
            'is_active' => false,
        ]);

        $this->postJson('/api/expert/auth/login', [
            'email' => $expert->email,
            'password' => $password,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expert_login_is_rate_limited_by_normalized_email_and_ip(): void
    {
        $email = fake()->unique()->safeEmail();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/expert/auth/login', [
                'email' => $email,
                'password' => Str::password(24),
            ])->assertUnauthorized();
        }

        $this->postJson('/api/expert/auth/login', [
            'email' => mb_strtoupper($email),
            'password' => Str::password(24),
        ])
            ->assertTooManyRequests()
            ->assertExactJson([
                'status' => false,
                'message' => 'Too many attempts. Please try again later.',
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{Expert, string}
     */
    private function createExpertWithKnownPassword(array $attributes = []): array
    {
        $password = Str::password(24);
        $expert = Expert::factory()->create([
            ...$attributes,
            'password' => Hash::make($password),
        ]);

        return [$expert, $password];
    }
}
