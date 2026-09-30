<?php

namespace Tests\Feature\Privacy;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class QueuedPrivacyIntegrationTest extends TestCase
{
    public function test_real_database_queue_worker_cache_and_private_artifact_lifecycle_across_processes(): void
    {
        $directory = sys_get_temp_dir().'/solveit-queue-test-'.bin2hex(random_bytes(12));
        mkdir($directory, 0700);
        mkdir($directory.'/storage/framework', 0700, true);
        file_put_contents($directory.'/.test-owned', 'disposable integration test');
        $environment = [
            'SOLVEIT_QUEUE_TEST_ROOT' => $directory, 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_CONFIG_CACHE' => $directory.'/config.php',
            'APP_ROUTES_CACHE' => $directory.'/routes.php', 'APP_SERVICES_CACHE' => $directory.'/services.php',
            'APP_PACKAGES_CACHE' => $directory.'/packages.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database.sqlite',
            'DB_URL' => '', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'MAIL_MAILER' => 'array',
            'BCRYPT_ROUNDS' => '4', 'LOG_CHANNEL' => 'single', 'SESSION_DRIVER' => 'array',
        ];
        $base = dirname(__DIR__, 3);
        $run = function (array $command, array $input = []) use ($base, $environment): string {
            $process = new Process($command, $base, $environment, json_encode($input, JSON_THROW_ON_ERROR), 45);
            $process->run();
            $this->assertSame(0, $process->getExitCode(), 'Isolated child process failed; output is suppressed to protect test credentials.');

            return $process->getOutput();
        };
        $phase = fn (string $name, array $input = []): array => json_decode($run([PHP_BINARY, 'tests/Support/privacy_queue_process.php', $name], $input), true, flags: JSON_THROW_ON_ERROR);
        $http = fn (string $token, string $method, string $url, array $body = [], ?string $key = null): array => $phase('http', ['token' => $token, 'method' => $method, 'url' => $url, 'body' => $body, 'idempotencyKey' => $key]);

        try {
            $setup = $phase('setup');
            $tokens = $setup['tokens'];
            $owner = $tokens['owner'];
            $run([PHP_BINARY, 'artisan', 'route:cache']);
            $confirm = $http($owner, 'POST', '/api/user/security/confirm-password', ['currentPassword' => 'password', 'purpose' => 'export']);
            $this->assertSame(200, $confirm['status']);
            $key = 'queue-export-'.bin2hex(random_bytes(12));
            $created = $http($owner, 'POST', '/api/user/data-requests', ['type' => 'export', 'scope' => 'account'], $key);
            $this->assertSame(202, $created['status']);
            $this->assertSame('requested', $created['body']['data']['item']['status']);
            $id = $created['body']['data']['item']['id'];
            $replay = $http($owner, 'POST', '/api/user/data-requests', ['type' => 'export', 'scope' => 'account'], $key);
            $this->assertSame($created['body'], $replay['body']);
            $this->assertSame('true', $replay['replayed']);
            $inspect = ['requestId' => $id, 'ownerId' => $setup['ownerId'], 'token' => $owner];
            $queued = $phase('inspect', $inspect);
            $this->assertSame(1, $queued['queued']);
            $this->assertFalse($queued['queuedPayloadContainsPrivateData']);
            $run([PHP_BINARY, 'artisan', 'queue:work', 'database', '--once', '--tries=3', '--timeout=30', '--sleep=0']);
            $completed = $phase('inspect', $inspect);
            $this->assertSame('completed', $completed['requestStatus']);
            $this->assertSame(0, $completed['queued']);
            $this->assertSame(0, $completed['failedJobs']);
            $this->assertTrue($completed['artifactEncrypted']);
            $this->assertTrue($completed['artifactVerified']);
            $this->assertSame(2, $completed['transitionCount']);
            $downloadUrl = '/api/user/data-requests/'.$id.'/download';
            $download = $http($owner, 'GET', $downloadUrl);
            $this->assertSame(200, $download['status']);
            $this->assertSame($setup['ownerId'], $download['body']['account']['id']);
            $this->assertStringContainsString('private', $download['cacheControl']);
            $this->assertStringContainsString('no-store', $download['cacheControl']);
            $this->assertStringContainsString('attachment;', $download['disposition']);
            foreach (['stranger' => 404, 'expert' => 403, 'admin' => 403, 'secondOwner' => 403] as $actor => $expected) {
                $this->assertSame($expected, $http($tokens[$actor], 'GET', $downloadUrl)['status']);
            }
            $this->assertSame(200, $http($owner, 'POST', '/api/user/security/confirm-password', ['currentPassword' => 'password', 'purpose' => 'deletion'])['status']);
            $deletion = $http($owner, 'POST', '/api/user/data-requests', ['type' => 'deletion', 'scope' => 'account'], 'queue-deletion-'.bin2hex(random_bytes(12)));
            $this->assertSame(202, $deletion['status']);
            $run([PHP_BINARY, 'artisan', 'queue:work', 'database', '--once', '--tries=3', '--timeout=30', '--sleep=0']);
            $deferred = $http($owner, 'GET', '/api/user/data-requests/'.$deletion['body']['data']['item']['id']);
            $this->assertSame('deferred', $deferred['body']['data']['item']['status']);
            $this->assertSame('RETENTION_POLICY_PENDING', $deferred['body']['data']['item']['reasonCode']);
            $phase('expire', ['requestId' => $id]);
            $this->assertSame(409, $http($owner, 'GET', $downloadUrl)['status']);
            $run([PHP_BINARY, 'artisan', 'privacy:cleanup']);
            $cleaned = $phase('inspect', $inspect);
            $this->assertSame(0, $cleaned['exportFiles']);
            $this->assertTrue($cleaned['ownerExists']);
            $this->assertSame(0, $cleaned['failedJobs']);
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }
}
