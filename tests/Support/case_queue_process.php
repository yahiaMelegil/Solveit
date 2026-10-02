<?php

// Disposable subprocess fixture only. Never expose this script via a web server.
use App\Models\CaseDocumentVersion;
use App\Models\CaseRecord;
use App\Models\CatalogVersion;
use App\Models\PolicyVersion;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\PrivacyDevelopmentSeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CatalogSupplyFixture;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$root = getenv('SOLVEIT_CASE_TEST_ROOT');
$phase = $argv[1] ?? '';
if (! $root || ! is_file($root.'/.test-owned') || realpath(dirname($root)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($root), 'solveit-case-test-') || ! in_array($phase, ['setup', 'http', 'upload', 'inspect'], true)) {
    exit(2);
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useStoragePath($root.'/storage');
$app->make(ConsoleKernel::class)->bootstrap();
try {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if ($phase === 'setup') {
        if (file_exists($root.'/database.sqlite')) {
            throw new RuntimeException('Expected fresh disposable DB.');
        }touch($root.'/database.sqlite');
        config()->set(['app.env' => 'testing', 'app.debug' => false, 'database.default' => 'sqlite', 'database.connections.sqlite.database' => $root.'/database.sqlite',
            'queue.default' => 'database', 'cache.default' => 'database', 'filesystems.disks.case-documents.root' => $root.'/documents',
            'case_intake.enabled_countries' => ['PS'], 'case_intake.scanner_binary' => $root.'/synthetic-scanner']);
        // Explicit test double, not evidence of a real malware scan.
        file_put_contents($root.'/synthetic-scanner', "#!/usr/bin/env php\n<?php exit(0);\n");
        chmod($root.'/synthetic-scanner', 0700);
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Migration failed.');
        }
        (new AuthorizationSeeder)->run();
        (new ServiceCatalogSeeder)->run();
        $v = CatalogVersion::firstOrFail();
        $p = $v->policy;
        $p['effectiveFrom'] = now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z');
        $p['commerciallyAvailable'] = true;
        $p['operationallyAvailable'] = true;
        $v = CatalogVersion::withoutEvents(function () use ($v, $p) {
            $v->forceFill(['status' => 'enabled', 'policy' => $p])->save();

            return $v;
        });
        $v->entry->forceFill(['current_version_id' => $v->id, 'status' => 'enabled'])->save();
        CatalogSupplyFixture::make($v);
        (new PrivacyDevelopmentSeeder)->run();
        $user = User::factory()->create(['email' => 'case-process@example.test']);
        file_put_contents(getenv('APP_CONFIG_CACHE'), '<?php return '.var_export(config()->all(), true).';');
        $out = ['catalogEntryId' => $v->entry_id, 'catalogVersion' => $v->version, 'token' => $user->createToken('case-process', [User::ACCESS_ABILITY])->plainTextToken,
            'policies' => PolicyVersion::query()->where('locale', 'en')->whereIn('purpose', ['terms', 'privacy'])->pluck('id', 'purpose')->all()];
    } elseif ($phase === 'inspect') {
        $version = CaseDocumentVersion::query()->first();
        $case = CaseRecord::find($input['caseId']);
        $out = ['queued' => DB::table('jobs')->count(), 'failedJobs' => DB::table('failed_jobs')->count(), 'scanStatus' => $version?->scan_status->value,
            'version' => $case?->version, 'caseStatus' => $case?->status->value, 'encrypted' => $version ? ! str_contains(Storage::disk($version->disk)->get($version->path), '%PDF') : false,
            'scratchFiles' => count(glob(storage_path('app/private/case-scan/*.tmp')) ?: []),
            'queuedPayloadContainsNarrative' => DB::table('jobs')->get()->contains(fn ($job) => str_contains($job->payload, 'private process narrative'))];
    } else {
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$input['token']];
        if (isset($input['key'])) {
            $server['HTTP_IDEMPOTENCY_KEY'] = $input['key'];
        }
        if ($phase === 'upload') {
            $temp = $root.'/fixture.pdf';
            file_put_contents($temp, "%PDF-1.4\n%%EOF\n");
            $request = Request::create($input['url'], 'POST', $input['body'], [], ['file' => new UploadedFile($temp, 'brief.pdf', 'application/pdf', test: true)], $server);
        } else {
            $server['CONTENT_TYPE'] = 'application/json';
            $request = Request::create($input['url'], $input['method'], server: $server, content: json_encode($input['body'] ?? [], JSON_THROW_ON_ERROR));
        }
        $kernel = $app->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $out = ['status' => $response->getStatusCode(), 'body' => str_contains((string) $response->headers->get('Content-Type'), 'json') ? json_decode($response->getContent(), true) : ['binaryLength' => strlen($response->getContent())], 'replayed' => $response->headers->get('Idempotency-Replayed')];
    }
    echo json_encode($out, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'Isolated case process failed: '.$e::class.PHP_EOL);
    exit(1);
}
