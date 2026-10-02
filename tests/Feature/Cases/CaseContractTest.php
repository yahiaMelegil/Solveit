<?php

namespace Tests\Feature\Cases;

use App\Enums\CaseDocumentScanStatus;
use App\Jobs\Cases\ScanCaseDocument;
use App\Models\Admin;
use App\Services\Cases\DocumentScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;

class CaseContractTest extends CaseTestCase
{
    private function capture(string $name, string $method, string $url, array $body = [], int $status = 200, bool $multipart = false): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($method !== 'GET') {
            $headers += $this->key();
        }
        $response = $multipart ? $this->post($url, $body, $headers) : $this->json($method, $url, $body, $headers);
        $response->assertStatus($status)->assertHeader('X-Request-ID');
        $output = $response->json();
        $directory = getenv('SOLVEIT_CASE_FIXTURE_DIR');
        if ($directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            if (isset($body['file'])) {
                $body['file'] = '<BINARY_PDF>';
            }
            $headers['Authorization'] = 'Bearer <ACCOUNT_TOKEN>';
            if (isset($headers['Idempotency-Key'])) {
                $headers['Idempotency-Key'] = '<UNIQUE_REQUEST_KEY>';
            }
            $headers['Content-Type'] = $multipart ? 'multipart/form-data' : 'application/json';
            file_put_contents($directory.'/'.$name.'.json', json_encode(['contractVersion' => '1.0.0', 'synthetic' => true, 'request' => ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => (object) $body],
                'response' => ['status' => $status, 'headers' => ['Content-Type' => $response->headers->get('Content-Type'), 'Cache-Control' => $response->headers->get('Cache-Control'), 'Retry-After' => $response->headers->get('Retry-After')], 'body' => $output]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        }

        return $output['data']['item'] ?? [];
    }

    public function test_contract_fixtures_cover_the_http_wizard_and_main_error_states(): void
    {
        RateLimiter::for('cases-create', fn () => Limit::perMinute(1000)->by('case-contract'));
        $this->capture('bootstrap', 'GET', '/api/user/case-intake/bootstrap');
        $case = $this->capture('draft-created', 'POST', '/api/user/cases', [], 201);
        $url = '/api/user/cases/'.$case['id'];
        $this->capture('incomplete-submit-422', 'POST', $url.'/submit', ['expectedVersion' => 1], 422);
        $case = $this->capture('needs-information', 'POST', $url.'/intake-assessments', ['expectedVersion' => 1]);
        $case = $this->capture('autosave-complete', 'PATCH', $url, ['expectedVersion' => $case['version']] + $this->input());
        $this->capture('draft-resumed', 'GET', $url);
        $this->capture('version-conflict-409', 'PATCH', $url, ['expectedVersion' => 1, 'title' => 'Stale'], 409);
        $case = $this->capture('suitable-assessment', 'POST', $url.'/intake-assessments', ['expectedVersion' => $case['version']]);
        $this->capture('clarifications', 'GET', $url.'/clarifications');
        $case = $this->capture('intake-confirmed', 'POST', $url.'/intake-confirmation', ['expectedVersion' => $case['version'], 'assessmentId' => $case['assessment']['id'], 'confirmed' => true]);
        $this->capture('legacy-submit-upgrade-required', 'POST', $url.'/submit', ['expectedVersion' => $case['version']], 409);
        $case = $this->prepareV2($case);
        $this->capture('ready-for-matching-v2', 'POST', '/api/v2/user/cases/'.$case['id'].'/submit', ['expectedVersion' => $case['version']]);
        $case = $this->current($case['id']);
        $this->capture('case-list', 'GET', '/api/user/cases?status=ready_for_matching&perPage=10');
        $this->capture('timeline', 'GET', $url.'/timeline?perPage=10');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['cases.viewAny', 'cases.view']);
        $this->asAccount($admin);
        $this->capture('admin-list', 'GET', '/api/admin/cases?status=ready_for_matching');
        $this->capture('admin-detail', 'GET', '/api/admin/cases/'.$case['id']);
        $this->capture('wrong-account-403', 'GET', $url, [], 403);
        $this->asAccount($this->owner);
        $this->capture('cancelled', 'POST', $url.'/cancel', ['expectedVersion' => $case['version']]);
        foreach (['urgent' => ['answers' => ['immediateDanger' => true, 'requiresInPerson' => false, 'ambiguousHighRisk' => false]], 'unsupported' => ['jurisdiction' => 'US'], 'human-triage-required' => ['answers' => ['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => true]]] as $name => $change) {
            $row = $this->createCase(array_replace($this->input(), $change));
            $this->capture($name, 'POST', '/api/user/cases/'.$row['id'].'/intake-assessments', ['expectedVersion' => 1]);
        }
        $context = $this->makeContext();
        $row = $this->createCase();
        $url = '/api/user/cases/'.$row['id'];
        $row = $this->capture('context-attached', 'POST', $url.'/context-snapshots', ['expectedVersion' => 1, 'contextId' => $context, 'contextVersion' => 1, 'selectedFactKeys' => ['goal'], 'authorizeUse' => true], 201);
        $row = $this->capture('context-detached', 'DELETE', $url.'/context-snapshots/'.$row['contextSnapshots'][0]['id'], ['expectedVersion' => $row['version']]);
        $doc = $this->capture('document-pending', 'POST', $url.'/documents', ['expectedVersion' => $row['version'], 'title' => 'API brief', 'category' => 'supporting', 'file' => UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\n%%EOF")], 201, true);
        $this->capture('document-not-ready-409', 'GET', $url.'/documents/'.$doc['id'].'/versions/'.$doc['currentVersion']['id'].'/download', [], 409);
        $this->capture('document-list', 'GET', $url.'/documents');
        $this->capture('document-versions', 'GET', $url.'/documents/'.$doc['id'].'/versions');
        foreach ([CaseDocumentScanStatus::Clean, CaseDocumentScanStatus::Failed, CaseDocumentScanStatus::Rejected] as $scanStatus) {
            if ($scanStatus !== CaseDocumentScanStatus::Clean) {
                $current = $this->current($row['id']);
                $doc = $this->capture('document-replacement-'.$scanStatus->value, 'POST', $url.'/documents/'.$doc['id'].'/versions', ['expectedVersion' => $current['version'], 'title' => 'Revised brief', 'category' => 'supporting', 'file' => UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\n%%EOF\n".$scanStatus->value)], 201, true);
            }
            $this->app->instance(DocumentScanner::class, new class($scanStatus) implements DocumentScanner
            {
                public function __construct(private CaseDocumentScanStatus $outcome) {}

                public function scan(string $privatePath): CaseDocumentScanStatus
                {
                    return $this->outcome;
                }
            });
            $this->app->call([new ScanCaseDocument($doc['currentVersion']['id']), 'handle']);
            $this->capture('document-'.$scanStatus->value, 'GET', $url.'/documents');
        }
        $doc['caseVersion'] = $this->current($row['id'])['version'];
        $this->capture('document-deleted', 'DELETE', $url.'/documents/'.$doc['id'], ['expectedVersion' => $doc['caseVersion']]);
        $this->capture('not-found-404', 'GET', '/api/user/cases/999999', [], 404);
        $this->capture('validation-422', 'POST', '/api/user/cases', ['owner' => 77], 422);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer invalid');
        $this->capture('guest-401', 'GET', '/api/user/cases', [], 401);
        $this->asAccount($this->owner);
        RateLimiter::for('cases-read', fn () => Limit::perMinute(1)->by('contract-limit'));
        $this->getJson('/api/user/cases')->assertOk();
        $this->capture('rate-limit-429', 'GET', '/api/user/cases', [], 429);
    }
}
