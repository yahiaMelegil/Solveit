<?php

namespace Tests\Feature\Authentication;

use App\Models\Expert;
use App\Models\User;
use App\Notifications\User\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_regular_user_specific_notification(): void
    {
        Notification::fake();
        config(['user.frontend.reset_password_url' => 'https://frontend.example/reset-password']);
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/forgot-password', [
            'email' => 'USER@example.com',
        ])
            ->assertOk()
            ->assertExactJson($this->genericForgotResponse());

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user): bool {
                $url = $notification->toMail($user)->actionUrl;

                return str_starts_with($url, 'https://frontend.example/reset-password?')
                    && str_contains($url, 'token=')
                    && str_contains($url, 'email=user%40example.com');
            },
        );
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_forgot_password_does_not_reveal_a_missing_account(): void
    {
        Notification::fake();

        $existingResponse = $this->postJson('/api/forgot-password', [
            'email' => User::factory()->create()->email,
        ]);
        $missingResponse = $this->postJson('/api/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $existingResponse->assertOk();
        $missingResponse->assertOk();
        $this->assertSame($existingResponse->json(), $missingResponse->json());
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();
        $email = User::factory()->create()->email;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/forgot-password', [
                'email' => $email,
            ])->assertOk();
        }

        $this->postJson('/api/forgot-password', [
            'email' => mb_strtoupper($email),
        ])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');
    }

    public function test_a_valid_reset_token_updates_the_password_revokes_tokens_and_allows_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $user->createToken('Web', [User::ACCESS_ABILITY]);
        $user->createToken('Mobile', [User::ACCESS_ABILITY]);
        $resetToken = Password::broker('users')->createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => mb_strtoupper($user->email),
            'token' => $resetToken,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Password reset successfully.',
                'data' => null,
            ]);

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'new-password123',
        ])->assertOk();
    }

    public function test_an_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
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
        $user = User::factory()->create();
        $resetToken = Password::broker('users')->createToken($user);
        $this->travel(61)->minutes();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
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
        $user = User::factory()->create();
        $resetToken = Password::broker('users')->createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'new-password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_regular_user_password_reset_tokens_are_isolated_from_experts(): void
    {
        $email = 'shared@example.com';
        $user = User::factory()->create(['email' => $email]);
        $expert = Expert::factory()->create(['email' => $email]);
        $userToken = Password::broker('users')->createToken($user);
        $expertToken = Password::broker('experts')->createToken($expert);

        $this->assertTrue(Password::broker('users')->tokenExists($user, $userToken));
        $this->assertTrue(Password::broker('experts')->tokenExists($expert, $expertToken));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $email]);
        $this->assertDatabaseHas('expert_password_reset_tokens', ['email' => $email]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $this->assertTrue(Password::broker('experts')->tokenExists($expert, $expertToken));
    }

    /**
     * @return array{status: bool, message: string, data: null}
     */
    private function genericForgotResponse(): array
    {
        return [
            'status' => true,
            'message' => 'If a user account exists for this email, a password reset link has been sent.',
            'data' => null,
        ];
    }
}
