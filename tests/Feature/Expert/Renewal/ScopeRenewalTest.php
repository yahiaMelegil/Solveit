<?php

namespace Tests\Feature\Expert\Renewal;

use App\Enums\AdminRole;
use App\Enums\ExpertKycStatus;
use App\Enums\ExpertScopeStatus;
use App\Models\Admin;
use App\Models\Expert;
use App\Models\ExpertKycApplication;
use App\Models\ExpertScopeRenewal;
use App\Models\ExpertVerifiedScope;
use App\Models\User;
use App\Services\Privacy\AuditWriter;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ExpertRenewalPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopeRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
        $this->seed(AuthorizationSeeder::class);
        Storage::fake('kyc');
    }

    private function scope(string $domain = 'legal', int $days = 15): ExpertVerifiedScope
    {
        $expert = Expert::factory()->verified()->create(['name' => 'Lina Haddad', 'country' => 'JO', 'domain' => $domain]);
        $expert->forceFill(['kyc_status' => ExpertKycStatus::Approved])->save();
        $app = ExpertKycApplication::factory()->create(['expert_id' => $expert->id, 'status' => 'verified', 'domain' => $domain, 'country' => 'JO', 'jurisdiction' => 'Jordan']);

        return $expert->verifiedScopes()->create(['kyc_application_id' => $app->id, 'domain' => $domain, 'jurisdiction' => 'Jordan', 'verified_country' => 'JO', 'role' => 'Consultant', 'languages' => ['ar', 'en'], 'service_types' => ['written_consultation'], 'status' => 'active', 'valid_from' => today()->subYear(), 'valid_until' => today()->addDays($days), 'next_review_at' => today()->addDays($days)]);
    }

    private function expert(ExpertVerifiedScope $scope): void
    {
        Sanctum::actingAs($scope->expert, [Expert::ACCESS_ABILITY]);
    }

    private function reviewer(): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::KycReviewer->value);
        Sanctum::actingAs($admin, [Admin::ACCESS_ABILITY]);

        return $admin;
    }

    private function create(ExpertVerifiedScope $scope): array
    {
        $this->expert($scope);

        return $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertCreated()->json('data.request');
    }

    private function upload(array $row, string $type = 'credential'): array
    {
        $evidence = match ($type) {
            'credential' => ['type' => 'credential', 'credentialType' => 'license', 'name' => 'Renewed professional license', 'issuer' => 'Example regulator', 'issueDate' => '2026-09-01', 'expiryDate' => '2027-08-01'],
            'qualification' => ['type' => 'qualification', 'degree' => 'Computer Science', 'institution' => 'Example University', 'graduationYear' => 2020],
            'experience' => ['type' => 'experience', 'jobTitle' => 'Software architect', 'organization' => 'Example Studio', 'description' => 'Evidence of recent architecture consulting.'],
        };

        return $this->post('/api/expert/scope-renewals/'.$row['id'].'/evidence', ['version' => $row['version'], 'evidence' => $evidence, 'file' => UploadedFile::fake()->create('renewal.pdf', 4, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated()->json('data.request');
    }

    private function submitted(ExpertVerifiedScope $scope, string $type = 'credential'): array
    {
        $row = $this->upload($this->create($scope), $type);

        return $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/submit', ['version' => $row['version']])->assertOk()->json('data.request');
    }

    private function review(array $row): array
    {
        $this->reviewer();

        return $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/start-review', ['version' => $row['version']])->assertOk()->json('data.request');
    }

    private function approval(array $row, bool $regulated = true): array
    {
        $payload = ['version' => $row['version'], 'evidenceReviewed' => true, 'validUntil' => '2027-03-30', 'nextReviewAt' => '2027-03-30'];
        if ($regulated) {
            $payload['professionalReview'] = ['verifiedCountry' => 'JO', 'regulator' => 'Example regulator', 'registrationNumber' => 'JO-EXAMPLE-321', 'verificationSource' => 'https://example.invalid/register/321', 'statusChecked' => 'active'];
        }

        return $payload;
    }

    public function test_regulated_approval_replaces_only_one_scope_and_preserves_kyc_and_history(): void
    {
        $scope = $this->scope();
        $other = $scope->replicate();
        $other->save();
        $row = $this->review($this->submitted($scope));
        $response = $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/approve', $this->approval($row))->assertOk()->assertJsonPath('data.request.status', 'approved');
        $replacement = ExpertVerifiedScope::findOrFail($response->json('data.request.replacementScopeId'));
        $this->assertTrue($replacement->isEffective());
        $this->assertSame($scope->expert_id, $replacement->expert_id);
        $this->assertSame('JO', $replacement->verified_country);
        $this->assertSame('2027-03-30', $replacement->valid_until->toDateString());
        $this->assertSame(ExpertScopeStatus::Revoked, $scope->refresh()->status);
        $this->assertSame('2026-10-15', $scope->valid_until->toDateString());
        $this->assertSame(ExpertScopeStatus::Active, $other->refresh()->status);
        $this->assertSame(ExpertKycStatus::Approved, $scope->expert->fresh()->kyc_status);
        $this->assertDatabaseHas('expert_kyc_applications', ['id' => $scope->kyc_application_id, 'status' => 'verified']);
        $this->assertDatabaseCount('expert_kyc_credentials', 1);
        $this->assertDatabaseCount('expert_kyc_documents', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'expert_renewal.approve', 'actor_type' => 'admin', 'previous_state' => 'under_review', 'new_state' => 'approved']);
        $this->assertDatabaseHas('expert_scope_renewals', ['id' => $row['id'], 'open_scope_id' => null]);
        $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/approve', $this->approval($row))->assertConflict();
        $this->assertDatabaseCount('expert_verified_scopes', 3);
        $this->assertStringNotContainsString('JO-EXAMPLE', DB::table('expert_scope_renewals')->value('review'));
    }

    public function test_nonregulated_experience_and_qualification_do_not_require_license(): void
    {
        foreach (['experience', 'qualification'] as $type) {
            $scope = $this->scope('technology');
            $row = $this->review($this->submitted($scope, $type));
            $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/approve', $this->approval($row, false))->assertOk()->assertJsonPath('data.request.status', 'approved');
            $this->assertDatabaseHas('expert_verified_scopes', ['expert_id' => $scope->expert_id, 'status' => 'active', 'evidence_type' => $type]);
        }
    }

    public function test_information_request_requires_new_evidence_and_preserves_old_submission(): void
    {
        $scope = $this->scope();
        $row = $this->review($this->submitted($scope));
        $row = $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/request-information', ['version' => $row['version'], 'reason' => 'Please provide the renewed license document.'])->assertOk()->json('data.request');
        $this->expert($scope);
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/submit', ['version' => $row['version']])->assertConflict()->assertJsonPath('code', 'NEW_EVIDENCE_REQUIRED');
        $first = $row['currentSubmissionId'];
        $row = $this->upload($row);
        $this->assertCount(2, $row['submissions']);
        $this->assertNotSame($first, $row['currentSubmissionId']);
        $this->get('/api/expert/scope-renewals/'.$row['id'].'/submissions/'.$first.'/document')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/submit', ['version' => $row['version']])->assertOk();
        $raw = DB::table('expert_scope_renewals')->value('history');
        $this->assertStringNotContainsString('Please provide', $raw);
    }

    public function test_rejection_does_not_revoke_valid_scope_and_allows_new_request(): void
    {
        $scope = $this->scope();
        $row = $this->review($this->submitted($scope));
        $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/reject', ['version' => $row['version'], 'reason' => 'The submitted evidence could not be verified.'])->assertOk();
        $this->assertTrue($scope->fresh()->isEffective());
        $this->assertNotSame($row['id'], $this->create($scope)['id']);
    }

    public function test_window_boundaries_duplicate_guard_and_expired_access(): void
    {
        $scope = $this->scope('legal', 31);
        $this->expert($scope);
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertConflict()->assertJsonPath('code', 'RENEWAL_WINDOW_NOT_OPEN');
        $scope->forceFill(['next_review_at' => today()->addDays(30)])->save();
        $row = $this->create($scope);
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertConflict()->assertJsonPath('code', 'OPEN_RENEWAL_EXISTS');
        $this->getJson('/api/expert/scope-renewals/scopes')->assertOk()->assertJsonPath('data.scopes.0.renewal.openRequestId', $row['id']);
        $expired = $this->scope('legal', -1);
        $this->submitted($expired);
        $this->assertFalse($expired->fresh()->isEffective());
        $expired->forceFill(['status' => 'expired'])->save();
        $this->expert($expired);
        $this->getJson('/api/expert/scope-renewals')->assertOk()->assertJsonCount(1, 'data.requests');
    }

    public function test_unsupported_suspended_revoked_and_legacy_scopes_fail_closed(): void
    {
        foreach (['suspended', 'revoked'] as $state) {
            $scope = $this->scope();
            $scope->forceFill(['status' => $state])->save();
            $this->expert($scope);
            $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertConflict();
        }
        $scope = $this->scope('unknown');
        $this->expert($scope);
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertConflict()->assertJsonPath('code', 'DOMAIN_POLICY_REQUIRED');
        $scope = $this->scope();
        $scope->forceFill(['verified_country' => null])->save();
        $this->expert($scope);
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertConflict()->assertJsonPath('code', 'LEGACY_REVIEW_REQUIRED');
        $scope = $this->scope();
        $scope->forceFill(['valid_until' => null, 'next_review_at' => null])->save();
        $this->expert($scope);
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertConflict()->assertJsonPath('code', 'REVIEW_DATE_REQUIRED');
    }

    public function test_account_types_ownership_guests_abilities_and_permission_separation(): void
    {
        $this->getJson('/api/expert/scope-renewals')->assertUnauthorized();
        $this->getJson('/api/admin/scope-renewals')->assertUnauthorized();
        $scope = $this->scope();
        $row = $this->submitted($scope);
        Sanctum::actingAs(Expert::factory()->verified()->create(), [Expert::ACCESS_ABILITY]);
        $this->getJson('/api/expert/scope-renewals/'.$row['id'])->assertNotFound();
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/cancel', [])->assertNotFound();
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertNotFound();
        foreach ([User::factory()->create(), Admin::factory()->create()] as $actor) {
            Sanctum::actingAs($actor, ['*']);
            $this->getJson('/api/expert/scope-renewals')->assertForbidden();
        }
        Sanctum::actingAs($scope->expert, []);
        $this->getJson('/api/expert/scope-renewals')->assertForbidden();
        Sanctum::actingAs($scope->expert, ['*']);
        $this->getJson('/api/admin/scope-renewals')->assertForbidden();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('experts.reviewKyc');
        Sanctum::actingAs($admin, [Admin::ACCESS_ABILITY]);
        $this->getJson('/api/admin/scope-renewals')->assertForbidden();
        $admin->givePermissionTo('expertRenewals.viewAny');
        $this->getJson('/api/admin/scope-renewals')->assertOk()->assertJsonMissingPath('data.requests.0.submissions')->assertJsonMissingPath('data.requests.0.feedback');
        $this->getJson('/api/admin/scope-renewals/'.$row['id'])->assertForbidden();
        $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/start-review', ['version' => $row['version']])->assertForbidden();
        $admin->givePermissionTo('expertRenewals.view');
        $this->getJson('/api/admin/scope-renewals/'.$row['id'])->assertOk();
        $this->getJson('/api/admin/scope-renewals/'.$row['id'].'/submissions/'.$row['currentSubmissionId'].'/document')->assertForbidden();
        $admin->givePermissionTo('expertRenewals.viewEvidence');
        $this->get('/api/admin/scope-renewals/'.$row['id'].'/submissions/'.$row['currentSubmissionId'].'/document')->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'expert_renewal.document_downloaded', 'actor_type' => 'admin', 'actor_id' => $admin->id]);
    }

    public function test_validation_unknown_fields_files_and_invalid_transitions(): void
    {
        $scope = $this->scope();
        $row = $this->create($scope);
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals', ['expert_id' => $scope->expert_id])->assertUnprocessable();
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/submit', ['version' => 1])->assertConflict();
        $this->post('/api/expert/scope-renewals/'.$row['id'].'/evidence', ['version' => 1, 'evidence' => ['type' => 'credential', 'credentialType' => 'license', 'name' => 'Test', 'issuer' => 'Test', 'path' => 'private'], 'file' => UploadedFile::fake()->create('bad.exe', 2, 'application/octet-stream')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->reviewer();
        $this->getJson('/api/admin/scope-renewals/'.$row['id'])->assertNotFound();
        $this->expert($scope);
        $row = $this->upload($row);
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/cancel', ['version' => 1])->assertConflict()->assertJsonPath('code', 'STALE_VERSION');
        $row = $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/cancel', ['version' => $row['version']])->assertOk()->json('data.request');
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/submit', ['version' => $row['version']])->assertConflict();
        $this->reviewer();
        $this->getJson('/api/admin/scope-renewals/'.$row['id'])->assertNotFound();
    }

    public function test_approval_revalidates_country_validity_license_and_scope_status(): void
    {
        $scope = $this->scope();
        $row = $this->review($this->submitted($scope));
        $url = '/api/admin/scope-renewals/'.$row['id'].'/approve';
        $payload = $this->approval($row);
        $payload['professionalReview']['verifiedCountry'] = 'US';
        $this->postJson($url, $payload)->assertUnprocessable();
        $payload = $this->approval($row);
        $payload['validUntil'] = '2027-09-01';
        $payload['nextReviewAt'] = '2027-09-01';
        $this->postJson($url, $payload)->assertUnprocessable();
        $payload = $this->approval($row);
        $payload['nextReviewAt'] = '2028-01-01';
        $this->postJson($url, $payload)->assertUnprocessable();
        $payload = $this->approval($row);
        unset($payload['professionalReview']);
        $this->postJson($url, $payload)->assertUnprocessable();
        $payload = $this->approval($row);
        $payload['evidenceReviewed'] = false;
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('expert_kyc_credentials', 0);
        $this->assertDatabaseCount('expert_kyc_documents', 0);
        $scope->forceFill(['status' => 'suspended'])->save();
        $this->postJson($url, $this->approval($row))->assertConflict();
        $this->assertDatabaseCount('expert_verified_scopes', 1);
    }

    public function test_corrupt_missing_and_foreign_documents_are_not_downloaded(): void
    {
        $scope = $this->scope();
        $row = $this->upload($this->create($scope));
        $other = $this->upload($this->create($this->scope()));
        $this->expert($scope);
        $this->getJson('/api/expert/scope-renewals/'.$row['id'].'/submissions/'.$other['currentSubmissionId'].'/document')->assertNotFound();
        $item = ExpertScopeRenewal::find($row['id'])->submissions()->first();
        Storage::disk('kyc')->put($item->path, 'corrupt');
        $this->getJson('/api/expert/scope-renewals/'.$row['id'].'/submissions/'.$item->id.'/document')->assertConflict();
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/submit', ['version' => $row['version']])->assertConflict();
        Storage::disk('kyc')->delete($item->path);
        $this->getJson('/api/expert/scope-renewals/'.$row['id'].'/submissions/'.$item->id.'/document')->assertConflict();
    }

    public function test_real_token_rate_limit_and_additive_seeder(): void
    {
        $scope = $this->scope();
        $token = $scope->expert->createToken('renewal', [Expert::ACCESS_ABILITY])->plainTextToken;
        $this->withToken($token)->getJson('/api/expert/scope-renewals')->assertOk();
        $this->withToken($token)->getJson('/api/expert/scope-renewals?perPage=101')->assertUnprocessable();
        for ($i = 0; $i < 30; $i++) {
            $this->withToken($token)->postJson('/api/expert/scopes/'.$scope->id.'/renewals');
        }
        $this->withToken($token)->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertStatus(429)->assertHeader('Retry-After');
        $role = Role::findByName('kyc_reviewer', 'admin');
        $role->givePermissionTo('users.view');
        $this->seed(ExpertRenewalPermissionsSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('users.view'));
        $this->assertTrue($role->fresh()->hasPermissionTo('expertRenewals.review'));
    }

    public function test_scope_expiring_during_review_stays_ineligible_until_approval(): void
    {
        $scope = $this->scope('legal', 0);
        $row = $this->review($this->submitted($scope));
        $this->travel(1)->days();
        $this->assertFalse($scope->fresh()->isEffective());
        $this->expert($scope);
        $this->postJson('/api/expert/scope-renewals/'.$row['id'].'/cancel', ['version' => $row['version']])->assertConflict();
        $this->post('/api/expert/scope-renewals/'.$row['id'].'/evidence', ['version' => $row['version'], 'evidence' => ['type' => 'credential', 'credentialType' => 'license', 'name' => 'Another license', 'issuer' => 'Example regulator'], 'file' => UploadedFile::fake()->create('new.pdf', 4, 'application/pdf')], ['Accept' => 'application/json'])->assertConflict();
        $this->reviewer();
        $response = $this->postJson('/api/admin/scope-renewals/'.$row['id'].'/approve', $this->approval($row))->assertOk();
        $this->assertTrue(ExpertVerifiedScope::find($response->json('data.request.replacementScopeId'))->isEffective());
    }

    public function test_database_enforces_one_open_request_and_failed_audit_rolls_back_upload(): void
    {
        $scope = $this->scope();
        $row = $this->create($scope);
        $copy = ExpertScopeRenewal::find($row['id'])->replicate();
        try {
            DB::transaction(fn () => $copy->save());
            $this->fail('Duplicate open scope was allowed.');
        } catch (UniqueConstraintViolationException $expected) {
            $this->assertDatabaseCount('expert_scope_renewals', 1);
        }
        $this->mock(AuditWriter::class)->shouldReceive('write')->once()->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->post('/api/expert/scope-renewals/'.$row['id'].'/evidence', ['version' => 1, 'evidence' => ['type' => 'credential', 'credentialType' => 'license', 'name' => 'Example license', 'issuer' => 'Example regulator'], 'file' => UploadedFile::fake()->create('test.pdf', 4, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR');
        $this->assertDatabaseCount('expert_scope_renewal_submissions', 0);
        $this->assertSame([], Storage::disk('kyc')->allFiles());
        $this->assertSame(1, ExpertScopeRenewal::find($row['id'])->version);
    }

    public function test_submission_history_is_immutable_and_evidence_is_encrypted(): void
    {
        $scope = $this->scope();
        $row = $this->upload($this->create($scope));
        $item = ExpertScopeRenewal::find($row['id'])->submissions()->first();
        $this->assertStringNotContainsString('Example regulator', DB::table('expert_scope_renewal_submissions')->value('evidence'));
        $this->expectException(\LogicException::class);
        $item->forceFill(['size' => 1])->save();
    }

    public function test_inactive_unverified_and_unapproved_experts_cannot_request_renewal(): void
    {
        $scope = $this->scope();
        $expert = $scope->expert;
        $expert->forceFill(['email_verified_at' => null])->save();
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
        $this->getJson('/api/expert/scope-renewals')->assertForbidden();
        $expert->forceFill(['email_verified_at' => now(), 'is_active' => false])->save();
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
        $this->getJson('/api/expert/scope-renewals')->assertForbidden();
        $expert->forceFill(['is_active' => true, 'kyc_status' => ExpertKycStatus::Pending])->save();
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
        $this->postJson('/api/expert/scopes/'.$scope->id.'/renewals')->assertConflict()->assertJsonPath('code', 'EXPERT_NOT_APPROVED');
    }

    private function fixture(string $name, string $method, string $url, array $body = [], int $status = 200, bool $upload = false): array
    {
        $response = $upload ? $this->post($url, $body, ['Accept' => 'application/json']) : $this->json($method, $url, $body);
        $response->assertStatus($status);
        $directory = getenv('SOLVEIT_RENEWAL_FIXTURE_DIR');
        if ($directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            if (isset($body['file'])) {
                $body['file'] = '<binary renewed-license.pdf>';
            }
            $fixture = ['contractVersion' => '1.0.0', 'synthetic' => true, 'request' => ['method' => $method, 'url' => $url, 'headers' => ['Authorization' => 'Bearer <ACCOUNT_TOKEN>', 'Accept' => 'application/json', 'Content-Type' => $upload ? 'multipart/form-data' : 'application/json'], 'body' => (object) $body],
                'response' => ['status' => $status, 'headers' => ['Cache-Control' => $response->headers->get('Cache-Control')], 'body' => $response->json()]];
            file_put_contents($directory.'/'.$name.'.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        }
        $this->assertStringNotContainsString('app/private', $response->getContent());
        $this->assertStringNotContainsString('checksum', $response->getContent());

        return $response->json('data.request') ?? [];
    }

    public function test_contract_fixtures_are_actual_responses_for_main_states(): void
    {
        $this->fixture('error-401', 'GET', '/api/expert/scope-renewals', status: 401);
        $scope = $this->scope();
        $this->expert($scope);
        $this->fixture('scopes-eligible', 'GET', '/api/expert/scope-renewals/scopes');
        $row = $this->fixture('draft', 'POST', '/api/expert/scopes/'.$scope->id.'/renewals', status: 201);
        $url = '/api/expert/scope-renewals/'.$row['id'];
        $adminUrl = '/api/admin/scope-renewals/'.$row['id'];
        $this->fixture('duplicate-409', 'POST', '/api/expert/scopes/'.$scope->id.'/renewals', status: 409);
        $this->fixture('validation-422', 'POST', $url.'/submit', status: 422);
        $this->fixture('expert-list', 'GET', '/api/expert/scope-renewals?status=draft&perPage=10&sort=oldest');
        $this->fixture('expert-detail', 'GET', $url);
        $evidence = ['type' => 'credential', 'credentialType' => 'license', 'name' => 'Renewed professional practice license', 'issuer' => 'Example Jordan regulator', 'issueDate' => '2026-09-01', 'expiryDate' => '2027-08-01'];
        $row = $this->fixture('evidence-uploaded', 'POST', $url.'/evidence', ['version' => $row['version'], 'evidence' => $evidence, 'file' => UploadedFile::fake()->create('renewed-license.pdf', 4, 'application/pdf')], 201, true);
        $row = $this->fixture('submitted', 'POST', $url.'/submit', ['version' => $row['version']]);
        $this->reviewer();
        $this->fixture('admin-queue', 'GET', '/api/admin/scope-renewals?status=submitted&perPage=10');
        $this->fixture('admin-detail', 'GET', $adminUrl);
        $row = $this->fixture('under-review', 'POST', $adminUrl.'/start-review', ['version' => $row['version']]);
        $row = $this->fixture('needs-information', 'POST', $adminUrl.'/request-information', ['version' => $row['version'], 'reason' => 'Please provide a clear renewed license with the expiry date.']);
        $this->expert($scope);
        $row = $this->fixture('evidence-replaced', 'POST', $url.'/evidence', ['version' => $row['version'], 'evidence' => $evidence, 'file' => UploadedFile::fake()->create('clear-license.pdf', 4, 'application/pdf')], 201, true);
        $row = $this->fixture('resubmitted', 'POST', $url.'/submit', ['version' => $row['version']]);
        $row = $this->review($row);
        $this->fixture('approved', 'POST', $adminUrl.'/approve', $this->approval($row));
        $scope = $this->scope();
        $row = $this->review($this->submitted($scope));
        $this->fixture('rejected', 'POST', '/api/admin/scope-renewals/'.$row['id'].'/reject', ['version' => $row['version'], 'reason' => 'The license status could not be confirmed with the regulator.']);
        $this->expert($scope);
        $row = $this->create($scope);
        $this->fixture('cancelled', 'POST', '/api/expert/scope-renewals/'.$row['id'].'/cancel', ['version' => $row['version']]);
        $this->fixture('error-404', 'GET', '/api/expert/scope-renewals/999999', status: 404);
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->fixture('error-403', 'GET', '/api/expert/scope-renewals', status: 403);
    }
}
