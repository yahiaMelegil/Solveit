<?php

namespace Tests\Feature\Expert\Auth;

use App\Enums\ExpertKycStatus;
use App\Models\Admin;
use App\Models\Expert;
use App\Models\User;
use App\Notifications\Expert\VerifyEmailNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expert_can_register_with_the_correct_initial_state_and_limited_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/expert/auth/register', [
            'name' => '  Ahmad Ali  ',
            'email' => 'Expert@Example.com',
            'country' => 'JO',
            'language' => 'ar',
            'domain' => 'legal',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => '  Expert Dashboard  ',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Expert account created successfully. Please verify your email address.')
            ->assertJsonPath('data.expert.name', 'Ahmad Ali')
            ->assertJsonPath('data.expert.email', 'expert@example.com')
            ->assertJsonPath('data.expert.country', 'JO')
            ->assertJsonPath('data.expert.language', 'ar')
            ->assertJsonPath('data.expert.domain', 'legal')
            ->assertJsonPath('data.expert.email_verified_at', null)
            ->assertJsonPath('data.expert.is_active', true)
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('data.kyc_status', ExpertKycStatus::NotSubmitted->value)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.expert.password')
            ->assertJsonMissingPath('data.expert.remember_token');

        $expert = Expert::query()->where('email', 'expert@example.com')->firstOrFail();
        $token = PersonalAccessToken::findToken($response->json('data.token'));

        $this->assertTrue(Hash::check('password123', $expert->password));
        $this->assertFalse($expert->hasVerifiedEmail());
        $this->assertSame('JO', $expert->country);
        $this->assertSame('ar', $expert->language);
        $this->assertSame('legal', $expert->domain);
        $this->assertSame(ExpertKycStatus::NotSubmitted, $expert->kyc_status);
        $this->assertNotNull($token);
        $this->assertTrue($token->can(Expert::ACCESS_ABILITY));
        $this->assertFalse($token->can('admin:access'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $expert->id,
            'tokenable_type' => Expert::class,
            'name' => 'Expert Dashboard',
        ]);
        Notification::assertSentTo($expert, VerifyEmailNotification::class);
    }

    public function test_registration_returns_consistent_validation_errors(): void
    {
        Notification::fake();

        $this->postJson('/api/expert/auth/register', [
            'name' => ' ',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The provided data is invalid.')
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('experts', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_registration_rejects_a_duplicate_expert_email_case_insensitively(): void
    {
        Expert::factory()->create(['email' => 'expert@example.com']);

        $this->postJson('/api/expert/auth/register', [
            'name' => 'Another Expert',
            'email' => 'EXPERT@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('experts', 1);
    }

    public function test_registration_requires_password_confirmation_to_match(): void
    {
        $this->postJson('/api/expert/auth/register', [
            'name' => 'Ahmad Ali',
            'email' => 'expert@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('experts', 0);
    }

    public function test_the_same_email_can_belong_to_different_account_types(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'shared@example.com']);
        Admin::factory()->create(['email' => 'shared@example.com']);

        $this->postJson('/api/expert/auth/register', [
            'name' => 'Shared Identity Expert',
            'email' => 'SHARED@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('experts', ['email' => 'shared@example.com']);
    }

    public function test_registration_rolls_back_when_token_creation_fails(): void
    {
        Notification::fake();
        Schema::drop('personal_access_tokens');
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/expert/auth/register', [
                'name' => 'Ahmad Ali',
                'email' => 'expert@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            throw new AssertionFailedError('Expected token creation to fail.');
        } catch (QueryException) {
            $this->assertDatabaseCount('experts', 0);
            Notification::assertNothingSent();
        }
    }
}
