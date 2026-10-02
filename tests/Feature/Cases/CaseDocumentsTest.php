<?php

namespace Tests\Feature\Cases;

use App\Enums\CaseDocumentScanStatus;
use App\Jobs\Cases\ScanCaseDocument;
use App\Models\CaseDocumentVersion;
use App\Models\CaseRecord;
use App\Models\User;
use App\Services\Cases\ClamAvDocumentScanner;
use App\Services\Cases\DocumentScanner;
use App\Services\Privacy\AuditWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class CaseDocumentsTest extends CaseTestCase
{
    private function pdf(string $suffix = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n".$suffix);
    }

    private function upload(array $case, ?int $document = null, ?UploadedFile $file = null, ?array $headers = null): array
    {
        $url = '/api/user/cases/'.$case['id'].'/documents'.($document ? '/'.$document.'/versions' : '');

        return $this->post($url, ['expectedVersion' => $case['version'], 'title' => 'Private supporting brief', 'category' => 'supporting', 'file' => $file ?? $this->pdf()], ($headers ?? $this->key()) + ['Accept' => 'application/json'])->assertCreated()->json('data.item');
    }

    private function scan(int $version, CaseDocumentScanStatus $result = CaseDocumentScanStatus::Clean): void
    {
        $this->app->instance(DocumentScanner::class, new class($result) implements DocumentScanner
        {
            public function __construct(private CaseDocumentScanStatus $result) {}

            public function scan(string $privatePath): CaseDocumentScanStatus
            {
                if (! is_file($privatePath)) {
                    throw new \RuntimeException('Missing scan scratch file');
                }

                return $this->result;
            }
        });
        $this->app->call([new ScanCaseDocument($version), 'handle']);
    }

    public function test_private_encrypted_document_scan_download_and_no_internal_paths(): void
    {
        $case = $this->createCase();
        $doc = $this->upload($case);
        $version = $doc['currentVersion']['id'];
        $url = '/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions/'.$version;
        $this->assertArrayNotHasKey('path', $doc['currentVersion']);
        $this->assertArrayNotHasKey('disk', $doc['currentVersion']);
        $raw = CaseDocumentVersion::findOrFail($version);
        $this->assertStringNotContainsString('%PDF', Storage::disk('case-documents')->get($raw->path));
        $this->getJson($url.'/download')->assertConflict()->assertJsonPath('code', 'DOCUMENT_NOT_READY');
        Queue::assertPushed(ScanCaseDocument::class);
        $this->scan($version);
        $response = $this->get($url.'/download', ['Accept' => 'application/json'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('%PDF', $response->getContent());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->getJson($url.'/preview')->assertConflict();
        $this->assertSame([], glob(storage_path('app/private/case-scan/*.tmp')) ?: []);
        $case = $this->ready($this->current($case['id']));
        $this->assertSame('ready_for_matching', $case['status']);
    }

    public function test_image_preview_replacement_history_and_logical_delete_revoke_download(): void
    {
        $case = $this->createCase();
        $doc = $this->upload($case, file: UploadedFile::fake()->image('scan.png', 2, 2));
        $v1 = $doc['currentVersion']['id'];
        $this->scan($v1);
        $this->get('/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions/'.$v1.'/preview')->assertOk()->assertHeader('Content-Type', 'image/png');
        $case = $this->current($case['id']);
        $new = $this->upload($case, $doc['id']);
        $this->assertSame(2, $new['currentVersion']['version']);
        $this->assertDatabaseCount('case_document_versions', 2);
        $this->getJson('/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions')->assertOk()->assertJsonPath('data.pagination.total', 2);
        $case = $this->current($case['id']);
        $this->mutation($case, 'submit', status: 422);
        $this->deleteJson('/api/user/cases/'.$case['id'].'/documents/'.$doc['id'], ['expectedVersion' => $case['version']], $this->key())->assertOk();
        $this->getJson('/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions/'.$v1.'/download')->assertNotFound();
        $this->getJson('/api/user/cases/'.$case['id'].'/documents')->assertOk()->assertJsonPath('data.pagination.total', 0);
        $this->assertCount(2, Storage::disk('case-documents')->allFiles('documents'));
    }

    public function test_upload_idempotency_uses_bytes_and_does_not_create_orphans_on_replay_or_conflict(): void
    {
        $case = $this->createCase();
        $key = $this->key();
        $doc = $this->upload($case, headers: $key);
        $replay = $this->upload($case, headers: $key);
        $this->assertSame($doc['id'], $replay['id']);
        $this->assertDatabaseCount('case_documents', 1);
        $this->post('/api/user/cases/'.$case['id'].'/documents', ['expectedVersion' => 1, 'title' => 'Private supporting brief', 'category' => 'supporting', 'file' => $this->pdf('changed')], $key + ['Accept' => 'application/json'])->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertCount(1, Storage::disk('case-documents')->allFiles());
    }

    public function test_foreign_nested_document_access_and_invalid_type_size_extension(): void
    {
        $case = $this->createCase();
        $doc = $this->upload($case);
        $otherCase = $this->createCase();
        $url = '/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions/'.$doc['currentVersion']['id'].'/download';
        $this->getJson('/api/user/cases/'.$otherCase['id'].'/documents/'.$doc['id'].'/versions/'.$doc['currentVersion']['id'].'/download')->assertNotFound();
        $this->asAccount(User::factory()->create());
        $this->getJson($url)->assertNotFound();
        $this->asAccount($this->owner);
        foreach ([UploadedFile::fake()->createWithContent('bad.pdf', '<script>unsafe</script>'), UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'), UploadedFile::fake()->createWithContent('wrong.png', "%PDF-1.4\n%%EOF")] as $file) {
            $this->post('/api/user/cases/'.$case['id'].'/documents', ['expectedVersion' => 2, 'title' => 'Bad file', 'category' => 'supporting', 'file' => $file], $this->key() + ['Accept' => 'application/json'])->assertUnprocessable();
        }
    }

    public function test_failed_rejected_missing_and_corrupt_files_are_never_downloadable(): void
    {
        $case = $this->createCase();
        $doc = $this->upload($case);
        $version = $doc['currentVersion']['id'];
        $url = '/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions/'.$version.'/download';
        $this->scan($version, CaseDocumentScanStatus::Rejected);
        $this->getJson($url)->assertConflict();
        $case = $this->current($case['id']);
        $doc = $this->upload($case, $doc['id']);
        $version = $doc['currentVersion']['id'];
        $this->app->instance(DocumentScanner::class, new ClamAvDocumentScanner);
        config()->set('case_intake.scanner_binary', null);
        $this->app->call([new ScanCaseDocument($version), 'handle']);
        $this->assertSame(CaseDocumentScanStatus::Failed, CaseDocumentVersion::find($version)->scan_status);
        $this->artisan('case-documents:retry', ['version' => $version])->assertSuccessful();
        $this->scan($version);
        $row = CaseDocumentVersion::find($version);
        Storage::disk('case-documents')->put($row->path, 'corrupt ciphertext');
        $this->getJson('/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions/'.$version.'/download')->assertConflict();
        Storage::disk('case-documents')->delete($row->path);
        $this->getJson('/api/user/cases/'.$case['id'].'/documents/'.$doc['id'].'/versions/'.$version.'/download')->assertConflict();
    }

    public function test_failed_audit_rolls_back_metadata_and_removes_uncommitted_file(): void
    {
        $case = $this->createCase();
        $this->mock(AuditWriter::class, function ($mock): void {
            $mock->shouldReceive('write')->andThrow(new \RuntimeException('test failure'));
        });
        $this->post('/api/user/cases/'.$case['id'].'/documents', ['expectedVersion' => 1, 'title' => 'Private', 'category' => 'supporting', 'file' => $this->pdf()], $this->key() + ['Accept' => 'application/json'])->assertStatus(500);
        $this->assertDatabaseCount('case_documents', 0);
        $this->assertDatabaseCount('case_document_versions', 0);
        $this->assertCount(0, Storage::disk('case-documents')->allFiles());
        $this->assertSame(1, CaseRecord::find($case['id'])->version);
    }

    public function test_cleanup_only_removes_stale_unreferenced_files(): void
    {
        $case = $this->createCase();
        $this->upload($case);
        Storage::disk('case-documents')->put('documents/orphan.enc', 'orphan');
        Storage::disk('case-documents')->put('documents/recent.enc', 'recent');
        touch(Storage::disk('case-documents')->path('documents/orphan.enc'), now()->subHours(25)->timestamp);
        $this->artisan('case-documents:cleanup')->assertSuccessful();
        Storage::disk('case-documents')->assertMissing('documents/orphan.enc');
        Storage::disk('case-documents')->assertExists('documents/recent.enc');
        $this->assertCount(2, Storage::disk('case-documents')->allFiles());
    }

    public function test_scan_job_replay_and_content_version_immutability(): void
    {
        $case = $this->createCase();
        $doc = $this->upload($case);
        $version = $doc['currentVersion']['id'];
        $this->scan($version);
        $current = $this->current($case['id']);
        $this->scan($version);
        $this->assertSame($current['version'], $this->current($case['id'])['version']);
        $this->expectException(\LogicException::class);
        CaseDocumentVersion::find($version)->forceFill(['checksum' => str_repeat('0', 64)])->save();
    }
}
