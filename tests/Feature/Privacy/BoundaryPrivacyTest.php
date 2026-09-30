<?php

namespace Tests\Feature\Privacy;

use App\Enums\DataRequestStatus;
use App\Jobs\Privacy\ProcessDataRightsRequest;
use App\Models\AuditEvent;
use App\Models\DataRightsRequest;
use App\Models\IdempotencyRecord;
use App\Models\User;
use App\Services\Privacy\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class BoundaryPrivacyTest extends PrivacyTestCase
{
    public function test_malformed_domain_and_nested_fields_return_validation_errors(): void
    {
        $data = $this->contextPayload();
        $data['domain'] = ['technology'];
        $this->postJson('/api/user/contexts', $data, $this->key())->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        $data = $this->contextPayload();
        $data['facts'][0]['user_id'] = 10;
        $this->postJson('/api/user/contexts', $data, $this->key())->assertUnprocessable();
        $this->assertDatabaseCount('specialized_contexts', 0);
    }

    public function test_context_noop_preserves_version_and_audit_count(): void
    {
        $id = $this->makeContext();
        $this->patchJson('/api/user/contexts/'.$id, ['expectedVersion' => 1, 'title' => $this->contextPayload()['title']])
            ->assertOk()->assertJsonPath('data.item.version', 1);
        $this->assertDatabaseCount('specialized_context_versions', 1);
        $this->assertSame(0, AuditEvent::where('action', 'user.context_updated')->count());
    }

    public function test_password_confirmation_preserves_significant_whitespace(): void
    {
        $this->asAccount(User::factory()->create(['password' => '  secret phrase  ']));
        $this->postJson('/api/user/security/confirm-password', ['currentPassword' => '  secret phrase  ', 'purpose' => 'export'])->assertOk();
        $this->postJson('/api/user/security/confirm-password', ['currentPassword' => 'secret phrase', 'purpose' => 'export'])->assertUnprocessable();
    }

    public function test_cleanup_recovers_dead_worker_and_operator_can_requeue_failed_request(): void
    {
        $id = $this->requestData();
        DataRightsRequest::findOrFail($id)->forceFill(['status' => 'processing', 'processing_token' => 'old-worker', 'processing_lease_until' => now()->subMinute()])->save();
        $this->artisan('privacy:cleanup')->assertSuccessful();
        $this->assertSame(DataRequestStatus::Failed, DataRightsRequest::find($id)->status);
        $this->assertNull(DataRightsRequest::find($id)->processing_token);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $id, 'reason_code' => 'PROCESSING_LEASE_EXPIRED']);
        $this->artisan('privacy:retry', ['request' => $id])->assertSuccessful();
        Queue::assertPushed(ProcessDataRightsRequest::class, 2);
        $this->process($id);
        $this->assertSame(DataRequestStatus::Completed, DataRightsRequest::find($id)->status);
        $this->artisan('privacy:retry', ['request' => $id])->assertFailed();
        $deletion = $this->requestData('deletion');
        $this->process($deletion);
        $this->artisan('privacy:retry', ['request' => $deletion])->assertFailed();
        $this->artisan('privacy:retry', ['request' => 'not-an-id'])->assertFailed();
    }

    public function test_material_policy_followed_by_editorial_revision_still_requires_reconsent(): void
    {
        $this->consent();
        $policy = $this->policy();
        foreach ([['version' => 'demo-2', 'requires_reconsent' => true], ['version' => 'demo-3', 'requires_reconsent' => false]] as $change) {
            $policy->replicate()->forceFill($change + ['effective_at' => now(), 'created_at' => now()])->save();
        }
        $this->getJson('/api/user/consents')->assertOk()->assertJsonPath('data.items.2.requiresReconsent', true)->assertJsonPath('data.items.2.effective', false);
    }

    public function test_server_failures_redact_response_and_log_and_rollback(): void
    {
        Log::spy();
        $this->mock(AuditWriter::class)->shouldReceive('write')->andThrow(new \RuntimeException('secret-phone-and-password'));
        $response = $this->patchJson('/api/user/profile', ['expectedVersion' => 0, 'name' => 'Private revised name']);
        $response->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR');
        $this->assertStringNotContainsString('secret-phone-and-password', $response->getContent());
        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertSame('Mariam Hasan', $this->owner->fresh()->name);
        Log::shouldHaveReceived('error')->once()->with('Sprint 1 API operation failed.', \Mockery::on(fn ($context) => array_keys($context) === ['exception_type', 'request_id']));
    }

    public function test_idempotency_response_is_encrypted_and_expiry_does_not_remove_duplicate_guard(): void
    {
        $id = $this->requestData();
        $stored = DB::table('idempotency_records')->first();
        $this->assertStringNotContainsString('Data request accepted', $stored->response_body);
        $this->assertSame($id, IdempotencyRecord::first()->response_body['data']['item']['id']);
        $this->travel(25)->hours();
        $this->confirm();
        $this->postJson('/api/user/data-requests', ['type' => 'export', 'scope' => 'account'], $this->key())
            ->assertConflict()->assertJsonPath('code', 'DUPLICATE_OPEN_REQUEST');
        $this->artisan('privacy:cleanup')->assertSuccessful();
        $this->assertDatabaseCount('idempotency_records', 0);
    }

    public function test_foreign_transitions_are_hidden_before_validation(): void
    {
        $context = $this->makeContext();
        $request = $this->requestData();
        $this->asAccount(User::factory()->create());
        $this->postJson('/api/user/contexts/'.$context.'/archive', [], $this->key())->assertNotFound();
        $this->postJson('/api/user/data-requests/'.$request.'/cancel', [], $this->key())->assertNotFound();
    }
}
