<?php

namespace Tests\Feature\Expert\Auth;

use App\Models\Expert;
use App\Models\User;
use App\Notifications\Expert\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_an_expert_specific_notification(): void
    {
        Notification::fake();
        config(['expert.frontend.reset_password_url' => 'https://frontend.example/expert/reset-password']);
        $expert = Expert::factory()->create(['email' => 'expert@example.com']);

        $this->postJson('/api/expert/auth/forgot-password', [
            'email' => 'EXPERT@example.com',
        ])
            ->assertOk()
            ->assertExactJson($this->genericForgotResponse());

        Notification::assertSentTo(
            $expert,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($expert): bool {
                $url = $notification->toMail($expert)->actionUrl;

                return str_starts_with($url, 'https://frontend.example/expert/reset-password?')
                    && str_contains($url, 'token=')
                    && str_contains($url, 'email=expert%40example.com');
            },
        );
        $this->assertDatabaseHas('expert_password_reset_tokens', [
            'email' => 'expert@example.com',
        ]);
    }

    public function test_forgot_password_does_not_reveal_a_missing_account(): void
    {
        Notification::fake();

        $existingResponse = $this->postJson('/api/expert/auth/forgot-password', [
            'email' => Expert::factory()->create()->email,
        ]);
        $missingResponse = $this->postJson('/api/expert/auth/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $existingResponse->assertOk();
        $missingResponse->assertOk();
        $this->assertSame($existingResponse->json(), $missingResponse->json());
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();
        $email = Expert::factory()->create()->email;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/expert/auth/forgot-password', [
                'email' => $email,
            ])->assertOk();
        }

        $this->postJson('/api/expert/auth/forgot-password', [
            'email' => mb_strtoupper($email),
        ])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');
    }

    public function test_a_valid_reset_token_updates_the_password_revokes_tokens_and_allows_login(): void
    {
        $expert = Expert::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $expert->createToken('Web', [Expert::ACCESS_ABILITY]);
        $expert->createToken('Mobile', [Expert::ACCESS_ABILITY]);
        $resetToken = Password::broker('experts')->createToken($expert);

        $this->postJson('/api/expert/auth/reset-password', [
            'email' => mb_strtoupper($expert->email),
            'token' => $resetToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Expert password reset successfully.',
                'data' => null,
            ]);

        $this->assertTrue(Hash::check('new-password123', $expert->fresh()->password));
        $this->assertDatabaseMissing('expert_password_reset_tokens', ['email' => $expert->email]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $expert->id,
            'tokenable_type' => Expert::class,
        ]);

        $this->postJson('/api/expert/auth/login', [
            'email' => $expert->email,
            'password' => 'new-password123',
        ])->assertOk();
    }

    public function test_an_invalid_reset_token_is_rejected(): void
    {
        $expert = Expert::factory()->create();

        $this->postJson('/api/expert/auth/reset-password', [
            'email' => $expert->email,
            'token' => 'invalid-token',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertUnprocessable()
            ->assertExactJson([
                'status' => false,
                'message' => 'The password reset token is invalid or has expired.',
                'errors' => [
                    'email' => ['The password reset token is invalid or has expired.'],
                ],
            ]);
    }

    public function test_an_expired_reset_token_is_rejected(): void
    {
        $expert = Expert::factory()->create();
        $resetToken = Password::broker('experts')->createToken($expert);
        $this->travel(61)->minutes();

        $this->postJson('/api/expert/auth/reset-password', [
            'email' => $expert->email,
            'token' => $resetToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The password reset token is invalid or has expired.');

        $this->travelBack();
    }

    public function test_password_confirmation_is_required_for_reset(): void
    {
        $expert = Expert::factory()->create();
        $resetToken = Password::broker('experts')->createToken($expert);

        $this->postJson('/api/expert/auth/reset-password', [
            'email' => $expert->email,
            'token' => $resetToken,
            'password' => 'new-password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_expert_password_reset_tokens_are_isolated_from_regular_users(): void
    {
        $email = 'shared@example.com';
        $expert = Expert::factory()->create(['email' => $email]);
        $user = User::factory()->create(['email' => $email]);
        $userToken = Password::broker('users')->createToken($user);
        $expertToken = Password::broker('experts')->createToken($expert);

        $this->assertTrue(Password::broker('users')->tokenExists($user, $userToken));
        $this->assertTrue(Password::broker('experts')->tokenExists($expert, $expertToken));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $email]);
        $this->assertDatabaseHas('expert_password_reset_tokens', ['email' => $email]);

        DB::table('expert_password_reset_tokens')->where('email', $email)->delete();

        $this->assertTrue(Password::broker('users')->tokenExists($user, $userToken));
    }

    /**
     * @return array{status: bool, message: string, data: null}
     */
    private function genericForgotResponse(): array
    {
        return [
            'status' => true,
            'message' => 'If an expert account exists for this email, a password reset link has been sent.',
            'data' => null,
        ];
    }
}
