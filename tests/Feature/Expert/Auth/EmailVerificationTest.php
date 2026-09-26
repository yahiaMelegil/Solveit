<?php

namespace Tests\Feature\Expert\Auth;

use App\Enums\ExpertKycStatus;
use App\Models\Expert;
use App\Notifications\Expert\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expert_can_resend_the_verification_notification(): void
    {
        Notification::fake();
        $expert = Expert::factory()->create();

        $this->withToken($this->expertToken($expert))
            ->postJson('/api/expert/auth/email/verification-notification')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Email verification link sent successfully.',
                'data' => ['email_verified' => false],
            ]);

        Notification::assertSentTo($expert, VerifyEmailNotification::class);
    }

    public function test_verification_notification_uses_the_configured_frontend_and_a_signed_backend_url(): void
    {
        config(['expert.frontend.verify_email_url' => 'https://frontend.example/expert/verify-email']);
        $expert = Expert::factory()->create();
        $mail = (new VerifyEmailNotification)->toMail($expert);

        $this->assertStringStartsWith(
            'https://frontend.example/expert/verify-email?',
            $mail->actionUrl,
        );

        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('verification_url', $query);
        $this->getJson($query['verification_url'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);
    }

    public function test_a_valid_signed_link_verifies_email_without_approving_kyc(): void
    {
        Event::fake([Verified::class]);
        $expert = Expert::factory()->create();

        $this->getJson($this->verificationUrl($expert))
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Email address verified successfully.',
                'data' => ['email_verified' => true],
            ]);

        $expert->refresh();

        $this->assertTrue($expert->hasVerifiedEmail());
        $this->assertSame(ExpertKycStatus::NotSubmitted, $expert->kyc_status);
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($expert));
    }

    public function test_an_invalid_or_modified_signature_is_rejected(): void
    {
        $expert = Expert::factory()->create();
        $unsignedUrl = route('expert.auth.email.verify', [
            'id' => $expert->id,
            'hash' => sha1($expert->email),
        ]);

        $this->getJson($unsignedUrl)
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'message' => 'The email verification link is invalid or has expired.',
            ]);

        $signedUrl = $this->verificationUrl($expert);
        $modifiedUrl = preg_replace(
            '#/verify/'.$expert->id.'/#',
            '/verify/'.($expert->id + 1).'/',
            $signedUrl,
            1,
        );

        $this->getJson($modifiedUrl)
            ->assertForbidden()
            ->assertJsonPath('message', 'The email verification link is invalid or has expired.');
    }

    public function test_an_expired_verification_link_is_rejected(): void
    {
        $expert = Expert::factory()->create();
        $expiredUrl = URL::temporarySignedRoute(
            'expert.auth.email.verify',
            now()->subMinute(),
            [
                'id' => $expert->id,
                'hash' => sha1($expert->getEmailForVerification()),
            ],
        );

        $this->getJson($expiredUrl)
            ->assertForbidden()
            ->assertJsonPath('message', 'The email verification link is invalid or has expired.');

        $this->assertFalse($expert->fresh()->hasVerifiedEmail());
    }

    public function test_verification_is_idempotent_for_an_already_verified_email(): void
    {
        $expert = Expert::factory()->verified()->create();

        $this->getJson($this->verificationUrl($expert))
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
        $expert = Expert::factory()->verified()->create();

        $this->withToken($this->expertToken($expert))
            ->postJson('/api/expert/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Email address is already verified.')
            ->assertJsonPath('data.email_verified', true);

        Notification::assertNothingSent();
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        Notification::fake();
        $expert = Expert::factory()->create();
        $token = $this->expertToken($expert);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->withToken($token)
                ->postJson('/api/expert/auth/email/verification-notification')
                ->assertOk();
        }

        $this->withToken($token)
            ->postJson('/api/expert/auth/email/verification-notification')
            ->assertTooManyRequests()
            ->assertExactJson([
                'status' => false,
                'message' => 'Too many attempts. Please try again later.',
            ]);
    }

    private function expertToken(Expert $expert): string
    {
        return $expert->createToken('Expert Dashboard', [Expert::ACCESS_ABILITY])->plainTextToken;
    }

    private function verificationUrl(Expert $expert): string
    {
        return URL::temporarySignedRoute(
            'expert.auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $expert->id,
                'hash' => sha1($expert->getEmailForVerification()),
            ],
        );
    }
}
