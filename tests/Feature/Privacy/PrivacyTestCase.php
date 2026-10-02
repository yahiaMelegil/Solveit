<?php

namespace Tests\Feature\Privacy;

use App\Jobs\Privacy\ProcessDataRightsRequest;
use App\Models\Admin;
use App\Models\Expert;
use App\Models\PolicyVersion;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\PrivacyDevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class PrivacyTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('data_rights.due_days', 30); // Synthetic test SLA, not a production/legal policy.
        Queue::fake();
        Storage::fake('data-exports');
        $this->freezeFixtureClock();
        $this->seed([AuthorizationSeeder::class, PrivacyDevelopmentSeeder::class]);
        $this->owner = User::factory()->create(['name' => 'Mariam Hasan', 'email' => 'mariam@example.com']);
        $this->asAccount($this->owner);
    }

    protected function freezeFixtureClock(): void {}

    protected function asAccount(User|Admin|Expert $actor, ?array $abilities = null): string
    {
        $this->app['auth']->forgetGuards();
        $token = $actor->createToken('privacy-test', $abilities ?? [$actor::ACCESS_ABILITY])->plainTextToken;
        $this->withToken($token);

        return $token;
    }

    protected function key(?string $key = null): array
    {
        return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
    }

    protected function contextPayload(): array
    {
        return ['domain' => 'technology', 'country' => 'PS', 'schemaVersion' => 1, 'title' => 'Store platform review',
            'facts' => [['key' => 'goal', 'value' => 'Review my store API', 'source' => 'user_reported', 'effectiveDate' => '2026-01-01']],
            'allowCaseReuse' => true];
    }

    protected function makeContext(): int
    {
        return $this->postJson('/api/user/contexts', $this->contextPayload(), $this->key())->assertCreated()->json('data.item.id');
    }

    protected function policy(string $purpose = 'marketing'): PolicyVersion
    {
        return PolicyVersion::query()->where('purpose', $purpose)->where('locale', 'en')->firstOrFail();
    }

    protected function consent(string $purpose = 'marketing'): int
    {
        return $this->postJson('/api/user/consents', ['purpose' => $purpose, 'policyVersionId' => $this->policy($purpose)->id,
            'decision' => 'granted'], $this->key())->assertCreated()->json('data.item.id');
    }

    protected function confirm(string $purpose = 'export'): void
    {
        $this->postJson('/api/user/security/confirm-password', ['currentPassword' => 'password', 'purpose' => $purpose])->assertOk();
    }

    protected function requestData(string $type = 'export'): int
    {
        $this->confirm($type);

        return $this->postJson('/api/user/data-requests', ['type' => $type, 'scope' => 'account'], $this->key())->assertAccepted()->json('data.item.id');
    }

    protected function process(int $id): void
    {
        app()->call([new ProcessDataRightsRequest($id), 'handle']);
    }
}
