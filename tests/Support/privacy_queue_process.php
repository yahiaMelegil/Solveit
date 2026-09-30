<?php

// Used only by QueuedPrivacyIntegrationTest; never serve this file over HTTP.
use App\Models\Admin;
use App\Models\DataRightsRequest;
use App\Models\Expert;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\PrivacyDevelopmentSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$directory = getenv('SOLVEIT_QUEUE_TEST_ROOT');
$phase = $argv[1] ?? '';
if (! $directory || ! is_file($directory.'/.test-owned')
    || realpath(dirname($directory)) !== realpath(sys_get_temp_dir())
    || ! str_starts_with(basename($directory), 'solveit-queue-test-')
    || ! in_array($phase, ['setup', 'http', 'inspect', 'expire'], true)) {
    exit(2);
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useStoragePath($directory.'/storage');
$app->make(ConsoleKernel::class)->bootstrap();

try {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if ($phase === 'setup') {
        if (is_file($directory.'/database.sqlite')) {
            throw new RuntimeException('The integration database must be new.');
        }
        touch($directory.'/database.sqlite');
        config()->set([
            'app.env' => 'testing', 'app.debug' => false,
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => $directory.'/database.sqlite',
            'queue.default' => 'database', 'cache.default' => 'database',
            'data_rights.due_days' => 30, // Synthetic operational SLA for this isolated test only.
            'filesystems.disks.data-exports.root' => $directory.'/exports',
        ]);
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Disposable migrations failed.');
        }
        (new AuthorizationSeeder)->run();
        (new PrivacyDevelopmentSeeder)->run();
        $owner = User::factory()->create(['name' => 'Queue test owner', 'email' => 'queue-owner@example.com']);
        $stranger = User::factory()->create(['email' => 'queue-stranger@example.com']);
        $expert = Expert::factory()->verified()->create();
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        // Same compiled config format used by Laravel. Subsequent processes share only disposable resources.
        file_put_contents(getenv('APP_CONFIG_CACHE'), '<?php return '.var_export(config()->all(), true).';');
        $output = ['ownerId' => $owner->id, 'tokens' => [
            'owner' => $owner->createToken('queue-test', [User::ACCESS_ABILITY])->plainTextToken,
            'secondOwner' => $owner->createToken('queue-test-other-token', [User::ACCESS_ABILITY])->plainTextToken,
            'stranger' => $stranger->createToken('queue-test', [User::ACCESS_ABILITY])->plainTextToken,
            'expert' => $expert->createToken('queue-test', [Expert::ACCESS_ABILITY, User::ACCESS_ABILITY])->plainTextToken,
            'admin' => $admin->createToken('queue-test', [Admin::ACCESS_ABILITY, User::ACCESS_ABILITY])->plainTextToken,
        ]];
    } elseif ($phase === 'http') {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$input['token']];
        if (isset($input['idempotencyKey'])) {
            $server['HTTP_IDEMPOTENCY_KEY'] = $input['idempotencyKey'];
        }
        $request = Request::create($input['url'], $input['method'], server: $server,
            content: json_encode($input['body'] ?? [], JSON_THROW_ON_ERROR));
        $kernel = $app->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $output = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
            'cacheControl' => $response->headers->get('Cache-Control'), 'disposition' => $response->headers->get('Content-Disposition'),
            'replayed' => $response->headers->get('Idempotency-Replayed')];
    } elseif ($phase === 'expire') {
        DataRightsRequest::findOrFail($input['requestId'])->forceFill(['artifact_expires_at' => now()->subMinute()])->save();
        $output = ['expired' => true];
    } else {
        $record = isset($input['requestId']) ? DataRightsRequest::findOrFail($input['requestId']) : null;
        $bytes = $record?->artifact_path ? Storage::disk('data-exports')->get($record->artifact_path) : null;
        $output = ['queued' => DB::table('jobs')->count(), 'failedJobs' => DB::table('failed_jobs')->count(),
            'requestStatus' => $record?->status->value, 'ownerExists' => User::whereKey($input['ownerId'])->exists(),
            'artifactEncrypted' => $bytes !== null && ! str_contains($bytes, 'queue-owner@example.com'),
            'artifactVerified' => $bytes !== null && hash_equals($record->artifact_checksum, hash('sha256', Crypt::decryptString($bytes))),
            'exportFiles' => count(Storage::disk('data-exports')->allFiles()),
            'queuedPayloadContainsPrivateData' => DB::table('jobs')->get()->contains(function ($job) use ($input): bool {
                foreach (['queue-owner@example.com', 'queue-stranger@example.com', 'currentPassword', $input['token'] ?? '__not_a_token__'] as $secret) {
                    if (str_contains($job->payload, $secret)) {
                        return true;
                    }
                }

                return false;
            }),
            'transitionCount' => DB::table('audit_events')->where('subject_type', 'data_request')->where('subject_id', $input['requestId'] ?? 0)->where('action', 'data_request.transitioned')->count()];
    }
    echo json_encode($output, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    // Do not emit request content, tokens, SQL bindings, or private paths on failure.
    fwrite(STDERR, 'Isolated privacy integration failed: '.$exception::class.PHP_EOL);
    exit(1);
}
