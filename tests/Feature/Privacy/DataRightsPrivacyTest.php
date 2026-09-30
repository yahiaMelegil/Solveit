<?php

namespace Tests\Feature\Privacy;

use App\Enums\DataRequestStatus;
use App\Exceptions\PrivacyException;
use App\Jobs\Privacy\ProcessDataRightsRequest;
use App\Models\AuditEvent;
use App\Models\DataRightsRequest;
use App\Models\Expert;
use App\Models\User;
use App\Services\Privacy\AccountExport;
use App\Services\Privacy\DataRightsManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class DataRightsPrivacyTest extends PrivacyTestCase
{
    public function test_export_creation_requires_confirmation_and_approved_sla(): void
    {
        $body = ['type' => 'export', 'scope' => 'account'];
        $this->postJson('/api/user/data-requests', $body, $this->key())->assertForbidden()->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');
        $this->confirm();
        config()->set('data_rights.due_days', null);
        $this->postJson('/api/user/data-requests', $body, $this->key())->assertConflict()->assertJsonPath('code', 'POLICY_CONFIGURATION_REQUIRED');
        $this->assertDatabaseCount('data_rights_requests', 0);
    }

    public function test_export_request_is_queued_and_duplicate_protected(): void
    {
        $this->confirm();
        $key = $this->key();
        $body = ['type' => 'export', 'scope' => 'account'];
        $id = $this->postJson('/api/user/data-requests', $body, $key)->assertAccepted()->assertJsonPath('data.item.status', 'requested')->json('data.item.id');
        $this->postJson('/api/user/data-requests', $body, $key)->assertAccepted()->assertHeader('Idempotency-Replayed', 'true');
        $this->postJson('/api/user/data-requests', $body, $this->key())->assertConflict()->assertJsonPath('code', 'DUPLICATE_OPEN_REQUEST');
        $this->assertDatabaseCount('data_rights_requests', 1);
        Queue::assertPushed(ProcessDataRightsRequest::class, fn ($job) => $job->requestId === $id);
    }

    public function test_actual_export_download_is_private_and_excludes_secrets_and_other_accounts(): void
    {
        Expert::factory()->create(['email' => $this->owner->email, 'name' => 'Private Expert Identity']);
        User::factory()->create(['email' => 'other-private@example.com']);
        $this->makeContext();
        $this->consent();
        $id = $this->requestData();
        $this->process($id);
        $record = DataRightsRequest::findOrFail($id);
        $this->assertSame('completed', $record->status->value);
        $this->assertSame(3, $record->version);
        Storage::disk('data-exports')->assertExists($record->artifact_path);
        $stored = Storage::disk('data-exports')->get($record->artifact_path);
        $this->assertStringNotContainsString('mariam@example.com', $stored);
        $this->getJson('/api/user/data-requests/'.$id)->assertOk()->assertJsonPath('data.item.downloadAvailable', true)
            ->assertJsonMissingPath('data.item.artifact_path')->assertJsonMissingPath('data.item.artifactPath');
        $response = $this->get('/api/user/data-requests/'.$id.'/download')->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $content = $response->getContent();
        $this->assertStringNotContainsString('Private Expert Identity', $content);
        $this->assertStringNotContainsString('other-private@example.com', $content);
        $this->assertStringNotContainsString($this->owner->password, $content);
        $this->assertStringNotContainsString('artifact_path', $content);
        $json = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('solveit.account-export.v1', $json['manifest']['formatVersion']);
        $this->assertSame($this->owner->id, $json['account']['id']);
        $this->assertCount(1, $json['contexts']);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.export_downloaded', 'subject_id' => $id]);
    }

    public function test_another_user_cannot_read_cancel_or_download_a_request(): void
    {
        $id = $this->requestData();
        $this->asAccount(User::factory()->create());
        $this->getJson('/api/user/data-requests/'.$id)->assertNotFound();
        $this->getJson('/api/user/data-requests/'.$id.'/download')->assertNotFound();
        $this->postJson('/api/user/data-requests/'.$id.'/cancel', ['expectedVersion' => 1], $this->key())->assertNotFound();
    }

    public function test_cancel_is_audited_and_cannot_be_processed_later(): void
    {
        $id = $this->requestData();
        $this->postJson('/api/user/data-requests/'.$id.'/cancel', ['expectedVersion' => 1], $this->key())->assertOk()->assertJsonPath('data.item.status', 'cancelled');
        $this->process($id);
        $this->assertSame('cancelled', DataRightsRequest::find($id)->status->value);
        $this->assertNull(DataRightsRequest::find($id)->artifact_path);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $id, 'new_state' => 'cancelled']);
    }

    public function test_completed_request_rejects_cancellation_and_stale_version(): void
    {
        $id = $this->requestData();
        $this->process($id);
        $this->postJson('/api/user/data-requests/'.$id.'/cancel', ['expectedVersion' => 1], $this->key())->assertConflict()->assertJsonPath('code', 'VERSION_CONFLICT');
        $this->postJson('/api/user/data-requests/'.$id.'/cancel', ['expectedVersion' => 3], $this->key())->assertConflict()->assertJsonPath('code', 'INVALID_STATE_TRANSITION');
    }

    public function test_deletion_is_real_tracked_and_deferred_without_deleting_account_or_context(): void
    {
        $context = $this->makeContext();
        $id = $this->requestData('deletion');
        $this->process($id);
        $this->getJson('/api/user/data-requests/'.$id)->assertOk()->assertJsonPath('data.item.status', 'deferred')
            ->assertJsonPath('data.item.reasonCode', 'RETENTION_POLICY_PENDING')->assertJsonPath('data.item.outcome.deleted', false)
            ->assertJsonPath('data.item.items.0.status', 'deferred');
        $this->assertDatabaseHas('users', ['id' => $this->owner->id]);
        $this->getJson('/api/user/contexts/'.$context)->assertOk();
        $this->postJson('/api/user/data-requests', ['type' => 'deletion', 'scope' => 'account'], $this->key())->assertConflict();
        $this->postJson('/api/user/data-requests/'.$id.'/cancel', ['expectedVersion' => 3], $this->key())->assertOk()->assertJsonPath('data.item.status', 'cancelled');
    }

    public function test_expired_missing_or_corrupt_artifact_never_downloads(): void
    {
        $id = $this->requestData();
        $this->process($id);
        $record = DataRightsRequest::findOrFail($id);
        $original = Storage::disk('data-exports')->get($record->artifact_path);
        Storage::disk('data-exports')->put($record->artifact_path, Crypt::encryptString('{"wrong":true}'));
        $this->getJson('/api/user/data-requests/'.$id.'/download')->assertConflict()->assertJsonPath('code', 'EXPORT_NOT_AVAILABLE');
        Storage::disk('data-exports')->put($record->artifact_path, $original);
        $this->travel(25)->hours();
        $this->confirm();
        $this->getJson('/api/user/data-requests/'.$id.'/download')->assertConflict();
        $this->artisan('privacy:cleanup')->assertSuccessful();
        Storage::disk('data-exports')->assertMissing($record->artifact_path);
        $this->assertNull($record->fresh()->artifact_path);
        $this->assertSame('completed', $record->fresh()->status->value);
    }

    public function test_password_confirmation_is_expiring_token_bound_and_purpose_bound(): void
    {
        $this->confirm();
        $this->postJson('/api/user/data-requests', ['type' => 'deletion', 'scope' => 'account'], $this->key())->assertForbidden();
        $this->asAccount($this->owner);
        $this->postJson('/api/user/data-requests', ['type' => 'export', 'scope' => 'account'], $this->key())->assertForbidden();
        $this->confirm();
        $this->travel(6)->minutes();
        $this->postJson('/api/user/data-requests', ['type' => 'export', 'scope' => 'account'], $this->key())->assertForbidden();
        $this->postJson('/api/user/security/confirm-password', ['currentPassword' => 'wrong', 'purpose' => 'export'])->assertUnprocessable();
    }

    public function test_worker_failure_records_safe_failure_and_retry_can_succeed(): void
    {
        $id = $this->requestData();
        $this->mock(AccountExport::class)->shouldReceive('build')->once()->andThrow(new \RuntimeException('PRIVATE PROVIDER CONTENT'));
        try {
            $this->process($id);
            $this->fail('Expected safe processing failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Data-rights processing failed.', $e->getMessage());
        }
        $this->assertSame('failed', DataRightsRequest::find($id)->status->value);
        $this->assertStringNotContainsString('PRIVATE PROVIDER CONTENT', AuditEvent::query()->get()->toJson());
        $this->app->forgetInstance(AccountExport::class);
        $this->process($id);
        $this->assertSame('completed', DataRightsRequest::find($id)->status->value);
    }

    public function test_duplicate_job_is_noop_after_completion(): void
    {
        $id = $this->requestData();
        $this->process($id);
        $events = AuditEvent::query()->where('subject_type', 'data_request')->where('subject_id', $id)->count();
        $path = DataRightsRequest::find($id)->artifact_path;
        $this->process($id);
        $this->assertSame($path, DataRightsRequest::find($id)->artifact_path);
        $this->assertSame($events, AuditEvent::query()->where('subject_type', 'data_request')->where('subject_id', $id)->count());
    }

    public function test_expired_processing_lease_is_recovered_without_duplicate_completion(): void
    {
        $id = $this->requestData();
        DataRightsRequest::find($id)->forceFill(['status' => 'processing', 'processing_token' => 'old-lease', 'processing_lease_until' => now()->subMinute()])->save();
        $this->process($id);
        $this->assertSame('completed', DataRightsRequest::find($id)->status->value);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $id, 'reason_code' => 'PROCESSING_LEASE_EXPIRED']);
    }

    public function test_invalid_transition_and_fake_completion_cannot_commit(): void
    {
        $id = $this->requestData('deletion');
        $manager = app(DataRightsManager::class);
        foreach ([DataRequestStatus::Completed, DataRequestStatus::Rejected] as $state) {
            try {
                DB::transaction(fn () => $manager->transition(DataRightsRequest::query()->lockForUpdate()->find($id), $state));
                $this->fail('Expected transition rejection');
            } catch (PrivacyException) {
                $this->assertSame('requested', DataRightsRequest::find($id)->status->value);
            }
        }
    }

    public function test_creation_does_not_accept_status_paths_or_ownership_fields(): void
    {
        $this->confirm();
        foreach (['user_id' => 123, 'status' => 'completed', 'artifactPath' => '/etc/passwd', 'dueAt' => '2020-01-01'] as $key => $value) {
            $this->postJson('/api/user/data-requests', ['type' => 'export', 'scope' => 'account', $key => $value], $this->key())->assertUnprocessable();
        }
        $this->assertDatabaseCount('data_rights_requests', 0);
    }
}
