<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use App\Notifications\User\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_the_regular_user_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Regular User',
            'email' => 'USER@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_a_user_can_resend_the_verification_notification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->withToken($this->userToken($user))
            ->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Email verification link sent successfully.',
                'data' => ['email_verified' => false],
            ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verification_notification_uses_the_configured_frontend_and_a_signed_backend_url(): void
    {
        config(['user.frontend.verify_email_url' => 'https://frontend.example/verify-email']);
        $user = User::factory()->unverified()->create();
        $mail = (new VerifyEmailNotification)->toMail($user);

        $this->assertStringStartsWith(
            'https://frontend.example/verify-email?',
            $mail->actionUrl,
        );

        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('verification_url', $query);
        $this->getJson($query['verification_url'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);
    }

    public function test_a_valid_signed_link_verifies_email(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $this->getJson($this->verificationUrl($user))
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Email address verified successfully.',
                'data' => ['email_verified' => true],
            ]);

        $user->refresh();

        $this->assertTrue($user->hasVerifiedEmail());
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($user));
    }

    public function test_an_invalid_or_modified_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $unsignedUrl = route('auth.email.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($unsignedUrl)
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'message' => 'The email verification link is invalid or has expired.',
            ]);

        $signedUrl = $this->verificationUrl($user);
        $modifiedUrl = preg_replace(
            '#/verify/'.$user->id.'/#',
            '/verify/'.($user->id + 1).'/',
            $signedUrl,
            1,
        );

        $this->getJson($modifiedUrl)
            ->assertForbidden()
            ->assertJsonPath('message', 'The email verification link is invalid or has expired.');
    }

    public function test_an_expired_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $expiredUrl = URL::temporarySignedRoute(
            'auth.email.verify',
            now()->subMinute(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($expiredUrl)
            ->assertForbidden()
            ->assertJsonPath('message', 'The email verification link is invalid or has expired.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_is_idempotent_for_an_already_verified_email(): void
    {
        $user = User::factory()->create();

        $this->getJson($this->verificationUrl($user))
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Email address is already verified.',
                'data' => ['email_verified' => true],
            ]);
    }

    public function test_resending_is_idempotent_when_email_is_already_verified(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->withToken($this->userToken($user))
            ->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Email address is already verified.')
            ->assertJsonPath('data.email_verified', true);

        Notification::assertNothingSent();
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = $this->userToken($user);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->withToken($token)
                ->postJson('/api/email/verification-notification')
                ->assertOk();
        }

        $this->withToken($token)
            ->postJson('/api/email/verification-notification')
            ->assertTooManyRequests()
            ->assertExactJson([
                'status' => false,
                'message' => 'Too many attempts. Please try again later.',
            ]);
    }

    private function userToken(User $user): string
    {
        return $user->createToken('Email Verification', [User::VERIFICATION_ABILITY])->plainTextToken;
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }
}
