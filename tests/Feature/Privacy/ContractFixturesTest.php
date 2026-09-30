<?php

namespace Tests\Feature\Privacy;

use App\Enums\DataRequestStatus;
use App\Models\Admin;
use App\Models\DataRightsRequest;
use App\Services\Privacy\DataRightsManager;
use Illuminate\Support\Facades\DB;

class ContractFixturesTest extends PrivacyTestCase
{
    private function capture(string $name, string $method, string $url, array $body = [], int $status = 200, bool $idempotent = false, string $note = ''): array
    {
        $headers = $idempotent ? $this->key() : [];
        $response = $this->json($method, $url, $body, $headers)->assertStatus($status);
        $output = $response->json();
        $directory = getenv('SOLVEIT_FIXTURE_DIR');
        if ($directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            if (isset($body['currentPassword'])) {
                $body['currentPassword'] = '<CURRENT_PASSWORD>';
            }
            $fixture = ['contractVersion' => '1.0.0', 'synthetic' => true, 'note' => $note,
                'request' => ['method' => $method, 'url' => $url, 'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer <ACCOUNT_TOKEN>'] + $headers, 'body' => (object) $body],
                'response' => ['status' => $status, 'headers' => array_filter([
                    'Content-Type' => $response->headers->get('Content-Type'), 'Cache-Control' => $response->headers->get('Cache-Control'),
                    'Content-Disposition' => $response->headers->get('Content-Disposition'), 'Retry-After' => $response->headers->get('Retry-After'),
                ]), 'body' => $output]];
            file_put_contents($directory.'/'.$name.'.json', json_encode($fixture, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
        }

        return $output;
    }

    public function test_documented_endpoint_examples_are_real_application_responses(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 29)->setTime(12, 0));
        $this->owner->forceFill(['created_at' => now()->subWeek(), 'updated_at' => now()->subWeek()])->save();
        $this->capture('profile-initial', 'GET', '/api/user/profile');
        $this->capture('profile-updated', 'PATCH', '/api/user/profile', ['expectedVersion' => 0, 'name' => 'مريم حسن', 'phone' => '+970599123456', 'country' => 'PS', 'language' => 'ar', 'timezone' => 'Asia/Hebron']);
        $this->capture('preferences-initial', 'GET', '/api/user/preferences');
        $this->capture('preferences-updated', 'PATCH', '/api/user/preferences', ['expectedVersion' => 0, 'contactChannels' => [], 'aiAssistanceEnabled' => true, 'recordingPreference' => false, 'contextVisibility' => 'private']);
        $this->capture('context-schemas', 'GET', '/api/user/context-schemas');
        $context = $this->capture('context-created', 'POST', '/api/user/contexts', $this->contextPayload(), 201, true)['data']['item']['id'];
        $url = '/api/user/contexts/'.$context;
        $this->capture('context-detail', 'GET', $url);
        $this->capture('context-list', 'GET', '/api/user/contexts?domain=technology&perPage=10&sortBy=updatedAt&sortDirection=desc');
        $facts = $this->contextPayload()['facts'];
        $facts[0]['value'] = 'Review a mobile booking API';
        $this->capture('context-conflict', 'PATCH', $url, ['expectedVersion' => 1, 'facts' => $facts]);
        $this->capture('context-clarified', 'PATCH', $url, ['expectedVersion' => 2, 'clarification' => 'I entered the old project goal by mistake.']);
        $this->capture('context-archived', 'POST', $url.'/archive', ['expectedVersion' => 3], 200, true);
        $this->capture('context-restored', 'POST', $url.'/restore', ['expectedVersion' => 4], 200, true);
        $this->capture('context-versions', 'GET', $url.'/versions?perPage=10&sortDirection=asc');
        $this->capture('context-deleted', 'DELETE', $url, ['expectedVersion' => 5], 200, true);
        $this->capture('policies', 'GET', '/api/user/policies', note: 'Synthetic policy texts, not approved legal policies.');
        $this->capture('consents-initial', 'GET', '/api/user/consents');
        $grant = ['purpose' => 'marketing', 'policyVersionId' => $this->policy()->id, 'decision' => 'granted'];
        $consent = $this->capture('consent-granted', 'POST', '/api/user/consents', $grant, 201, true)['data']['item']['id'];
        $this->capture('consents-effective', 'GET', '/api/user/consents');
        $this->capture('consent-withdrawn', 'POST', '/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $this->policy()->id, 'decision' => 'withdrawn', 'previousRecordId' => $consent], 201, true);
        $this->capture('consent-declined', 'POST', '/api/user/consents', ['purpose' => 'ai_assistance', 'policyVersionId' => $this->policy('ai_assistance')->id, 'decision' => 'declined'], 201, true);
        $this->capture('consent-regranted', 'POST', '/api/user/consents', $grant, 201, true);
        $text = 'Synthetic material update for contract testing only.';
        $this->policy()->replicate()->forceFill(['version' => 'demo-2', 'content' => $text, 'content_hash' => hash('sha256', $text),
            'effective_at' => now(), 'created_at' => now(), 'requires_reconsent' => true])->save();
        $this->capture('consents-reconsent-required', 'GET', '/api/user/consents');
        $this->capture('consent-history', 'GET', '/api/user/consents/history?purpose=marketing&sortBy=decidedAt');
        $this->capture('password-confirmed', 'POST', '/api/user/security/confirm-password', ['currentPassword' => 'password', 'purpose' => 'export']);
        $id = $this->capture('export-requested', 'POST', '/api/user/data-requests', ['type' => 'export', 'scope' => 'account'], 202, true, '30 days is a synthetic test SLA only.')['data']['item']['id'];
        $requestUrl = '/api/user/data-requests/'.$id;
        DB::transaction(function () use ($id): void {
            app(DataRightsManager::class)->transition(DataRightsRequest::query()->lockForUpdate()->findOrFail($id), DataRequestStatus::Processing);
        });
        $this->capture('export-processing', 'GET', $requestUrl, note: 'Worker state; there is no HTTP status mutation endpoint.');
        DB::transaction(function () use ($id): void {
            $row = DataRightsRequest::query()->lockForUpdate()->findOrFail($id);
            $row->items()->update(['status' => 'failed', 'reason_code' => 'PROCESSING_FAILED']);
            app(DataRightsManager::class)->transition($row, DataRequestStatus::Failed, reason: 'PROCESSING_FAILED');
        });
        $this->capture('export-failed', 'GET', $requestUrl);
        $this->process($id);
        $this->capture('export-completed', 'GET', $requestUrl);
        $this->capture('export-download', 'GET', $requestUrl.'/download');
        $this->confirm('deletion');
        $deletion = $this->capture('deletion-requested', 'POST', '/api/user/data-requests', ['type' => 'deletion', 'scope' => 'account'], 202, true)['data']['item']['id'];
        $this->process($deletion);
        $this->capture('deletion-deferred', 'GET', '/api/user/data-requests/'.$deletion, note: 'No deletion or anonymization was executed.');
        $this->capture('deletion-cancelled', 'POST', '/api/user/data-requests/'.$deletion.'/cancel', ['expectedVersion' => 3], 200, true);
        $reserved = $this->requestData('deletion');
        DB::transaction(function () use ($reserved): void {
            $row = DataRightsRequest::query()->lockForUpdate()->findOrFail($reserved);
            app(DataRightsManager::class)->transition($row, DataRequestStatus::Processing);
            app(DataRightsManager::class)->transition($row, DataRequestStatus::Rejected, reason: 'TEST_REJECTION_ONLY');
        });
        $this->capture('request-rejected-reserved', 'GET', '/api/user/data-requests/'.$reserved, note: 'Reserved state seeded internally for UI rendering; no rejection workflow ships in Sprint 1.');
        $this->capture('data-request-list', 'GET', '/api/user/data-requests?perPage=10&sortBy=dueAt');
        $this->capture('error-404', 'GET', '/api/user/contexts/999999', status: 404);
        $this->capture('error-409', 'PATCH', '/api/user/profile', ['expectedVersion' => 0, 'name' => 'A stale edit'], 409);
        $this->capture('error-422', 'PATCH', '/api/user/profile', ['expectedVersion' => 1, 'user_id' => 999], 422);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['users.consentMetadata.view', 'dataRequests.viewAny', 'dataRequests.view']);
        $this->asAccount($admin);
        $this->capture('admin-consent-metadata', 'GET', '/api/admin/users/'.$this->owner->id.'/consents');
        $this->capture('admin-request-list', 'GET', '/api/admin/data-requests?userId='.$this->owner->id);
        $this->capture('admin-request-detail', 'GET', '/api/admin/data-requests/'.$id);
        $this->capture('error-403', 'GET', '/api/user/profile', status: 403);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer invalid');
        $this->capture('error-401', 'GET', '/api/user/profile', status: 401);
        $this->asAccount($this->owner);
        $this->capture('error-403-reauthentication', 'GET', $requestUrl.'/download', status: 403);
        $this->travel(25)->hours();
        $this->confirm();
        $this->capture('export-expired', 'GET', $requestUrl);
        $this->capture('error-409-export-expired', 'GET', $requestUrl.'/download', status: 409);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/user/security/confirm-password', ['currentPassword' => 'wrong', 'purpose' => 'export']);
        }
        $this->capture('error-429', 'POST', '/api/user/security/confirm-password', ['currentPassword' => 'wrong', 'purpose' => 'export'], 429);
        $this->assertDatabaseHas('users', ['id' => $this->owner->id]);
    }
}
