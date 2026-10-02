<?php

namespace Tests\Feature\Cases;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CaseQueuedIntegrationTest extends TestCase
{
    public function test_cached_routes_database_worker_encrypted_document_and_catalog_v2_submission_across_processes(): void
    {
        $root = sys_get_temp_dir().'/solveit-case-test-'.bin2hex(random_bytes(10));
        mkdir($root.'/storage/framework', 0700, true);
        file_put_contents($root.'/.test-owned', 'disposable');
        $env = ['SOLVEIT_CASE_TEST_ROOT' => $root, 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_CONFIG_CACHE' => $root.'/config.php', 'APP_ROUTES_CACHE' => $root.'/routes.php', 'APP_SERVICES_CACHE' => $root.'/services.php', 'APP_PACKAGES_CACHE' => $root.'/packages.php',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $root.'/database.sqlite', 'DB_URL' => '', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4', 'LOG_CHANNEL' => 'single', 'SESSION_DRIVER' => 'array'];
        $base = dirname(__DIR__, 3);
        $run = function (array $command, array $input = []) use ($base, $env): string {
            $process = new Process($command, $base, $env, json_encode($input, JSON_THROW_ON_ERROR), 45);
            $process->run();
            $this->assertSame(0, $process->getExitCode(), 'Child process failed. Output withheld to avoid disclosing test tokens.');

            return $process->getOutput();
        };
        $phase = fn ($name, $input = []) => json_decode($run([PHP_BINARY, 'tests/Support/case_queue_process.php', $name], $input), true, flags: JSON_THROW_ON_ERROR);
        try {
            $setup = $phase('setup');
            $token = $setup['token'];
            $run([PHP_BINARY, 'artisan', 'route:cache']);
            $http = fn ($method, $url, $body = []) => $phase('http', ['token' => $token, 'method' => $method, 'url' => $url, 'body' => $body, 'key' => 'case-process-'.bin2hex(random_bytes(10))]);
            foreach ($setup['policies'] as $purpose => $policy) {
                $this->assertSame(201, $http('POST', '/api/user/consents', ['purpose' => $purpose, 'policyVersionId' => $policy, 'decision' => 'granted'])['status']);
            }
            $created = $http('POST', '/api/user/cases', ['title' => 'Process API', 'problemDescription' => 'A private process narrative about software reliability.', 'desiredOutcome' => 'A stable service.', 'primaryDomain' => 'technology', 'domains' => ['technology'], 'serviceNeeds' => ['document_review'], 'jurisdiction' => 'PS', 'language' => 'en', 'urgency' => 'normal', 'answers' => ['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => false]]);
            $this->assertSame(201, $created['status']);
            $case = $created['body']['data']['item'];
            $url = '/api/user/cases/'.$case['id'];
            $upload = $phase('upload', ['token' => $token, 'url' => $url.'/documents', 'body' => ['expectedVersion' => 1, 'title' => 'Private brief', 'category' => 'supporting'], 'key' => 'upload-'.bin2hex(random_bytes(10))]);
            $this->assertSame(201, $upload['status']);
            $before = $phase('inspect', ['caseId' => $case['id']]);
            $this->assertSame(1, $before['queued']);
            $this->assertSame('pending', $before['scanStatus']);
            $this->assertFalse($before['queuedPayloadContainsNarrative']);
            $run([PHP_BINARY, 'artisan', 'queue:work', 'database', '--once', '--tries=3', '--timeout=60', '--sleep=0']);
            $after = $phase('inspect', ['caseId' => $case['id']]);
            $this->assertSame('clean', $after['scanStatus']);
            $this->assertSame(0, $after['queued']);
            $this->assertSame(0, $after['failedJobs']);
            $this->assertTrue($after['encrypted']);
            $this->assertSame(0, $after['scratchFiles']);
            $assess = $http('POST', $url.'/intake-assessments', ['expectedVersion' => $after['version']]);
            $this->assertSame(200, $assess['status']);
            $case = $assess['body']['data']['item'];
            $confirmed = $http('POST', $url.'/intake-confirmation', ['expectedVersion' => $case['version'], 'assessmentId' => $case['assessment']['id'], 'confirmed' => true]);
            $this->assertSame(200, $confirmed['status']);
            $case = $confirmed['body']['data']['item'];
            $ready = $http('POST', $url.'/submit', ['expectedVersion' => $case['version']]);
            $this->assertSame(409, $ready['status']);
            $this->assertSame('CATALOG_UPGRADE_REQUIRED', $ready['body']['code']);
            $v2 = '/api/v2/user/cases/'.$case['id'];
            $selected = $http('PUT', $v2.'/scopes', ['expectedVersion' => $case['version'], 'scopes' => [['catalogEntryId' => $setup['catalogEntryId'], 'catalogVersion' => $setup['catalogVersion'], 'deliveryMode' => 'document_review', 'jurisdictionCodes' => [], 'answers' => [], 'confirmed' => true]]]);
            $this->assertSame(200, $selected['status']);
            $confirmed = $http('POST', $v2.'/confirm', ['expectedVersion' => $selected['body']['data']['item']['version'], 'confirmed' => true]);
            $this->assertSame(200, $confirmed['status']);
            $ready = $http('POST', $v2.'/submit', ['expectedVersion' => $confirmed['body']['data']['item']['version']]);
            $this->assertSame(200, $ready['status']);
            $this->assertSame('ready_for_matching', $ready['body']['data']['item']['status']);
            $doc = $upload['body']['data']['item'];
            $download = $http('GET', $url.'/documents/'.$doc['id'].'/versions/'.$doc['currentVersion']['id'].'/download');
            $this->assertSame(200, $download['status']);
            $this->assertGreaterThan(0, $download['body']['binaryLength']);
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }
}
