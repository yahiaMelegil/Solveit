<?php

namespace Tests\Feature\Expert\Kyc;

use App\Models\Admin;
use App\Models\Expert;
use App\Models\ExpertKycApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpertKycRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('kyc');
    }

    public function test_workspace_rejects_guests_other_account_types_and_unverified_experts(): void
    {
        $this->getJson('/api/expert/kyc')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(), [User::ACCESS_ABILITY]);
        $this->getJson('/api/expert/kyc')->assertForbidden();

        Sanctum::actingAs(Admin::factory()->create(), [Admin::ACCESS_ABILITY]);
        $this->getJson('/api/expert/kyc')->assertForbidden();

        Sanctum::actingAs(Expert::factory()->create(), [Expert::ACCESS_ABILITY]);
        $this->getJson('/api/expert/kyc')->assertForbidden();

        Sanctum::actingAs(Expert::factory()->verified()->create(), ['unrelated:ability']);
        $this->getJson('/api/expert/kyc')->assertForbidden();
    }

    public function test_verified_expert_can_use_kyc_workspace_before_approval(): void
    {
        $expert = Expert::factory()->verified()->create([
            'name' => 'Amina',
            'country' => 'JO',
            'domain' => 'technology',
        ]);
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);

        $this->getJson('/api/expert/kyc')
            ->assertOk()
            ->assertJsonPath('data.kycStatus', 'not_submitted')
            ->assertJsonPath('data.prefill.fullName', 'Amina')
            ->assertJsonPath('data.application', null);

        $this->putJson('/api/expert/kyc', [
            'jurisdiction' => 'Global remote services',
        ])->assertOk()->assertJsonPath('data.application.status', 'draft');
    }

    public function test_submission_requires_identity_and_professional_evidence(): void
    {
        $expert = Expert::factory()->verified()->create();
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
        $this->putJson('/api/expert/kyc', [
            'jurisdiction' => 'Global remote services',
        ])->assertOk();

        $this->postJson('/api/expert/kyc/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identityEvidence', 'cv']);

        $this->assertDatabaseHas('expert_kyc_applications', [
            'expert_id' => $expert->id,
            'status' => 'draft',
        ]);
    }

    public function test_expert_can_upload_and_download_own_private_document_without_exposing_path(): void
    {
        $expert = Expert::factory()->verified()->create();
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
        $this->putJson('/api/expert/kyc', ['jurisdiction' => 'Global remote services'])->assertOk();

        $response = $this->post('/api/expert/kyc/documents', [
            'documentType' => 'identity',
            'file' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonMissingPath('data.document.path')
            ->assertJsonMissingPath('data.document.disk');

        $document = $expert->kycApplications()->firstOrFail()->documents()->firstOrFail();
        $this->assertSame($document->id, $response->json('data.document.id'));
        Storage::disk('kyc')->assertExists($document->path);
        $this->get("/api/expert/kyc/documents/{$document->id}")->assertOk();

        $this->deleteJson("/api/expert/kyc/documents/{$document->id}")->assertOk();
        Storage::disk('kyc')->assertMissing($document->path);
    }

    public function test_expert_cannot_download_or_delete_another_experts_document(): void
    {
        $owner = Expert::factory()->verified()->create();
        Sanctum::actingAs($owner, [Expert::ACCESS_ABILITY]);
        $this->putJson('/api/expert/kyc', ['jurisdiction' => 'Global remote services'])->assertOk();
        $this->post('/api/expert/kyc/documents', [
            'documentType' => 'identity',
            'file' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $document = $owner->kycApplications()->firstOrFail()->documents()->firstOrFail();

        Sanctum::actingAs(Expert::factory()->verified()->create(), [Expert::ACCESS_ABILITY]);
        $this->get("/api/expert/kyc/documents/{$document->id}")->assertNotFound();
        $this->deleteJson("/api/expert/kyc/documents/{$document->id}")->assertNotFound();

        $this->assertDatabaseHas('expert_kyc_documents', ['id' => $document->id]);
        Storage::disk('kyc')->assertExists($document->path);
    }

    public function test_submitted_documents_cannot_be_changed_by_the_expert(): void
    {
        $expert = Expert::factory()->verified()->create();
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
        $application = $this->submitApplication($expert);
        $document = $application->documents()->firstOrFail();

        $this->deleteJson("/api/expert/kyc/documents/{$document->id}")->assertConflict();
        $this->post('/api/expert/kyc/documents', [
            'documentType' => 'identity',
            'file' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertConflict();
        $this->putJson('/api/expert/kyc', ['jurisdiction' => 'Changed'])->assertConflict();

        $this->assertSame(2, $application->documents()->count());
    }

    public function test_repeated_submission_is_rejected_without_duplicating_state_history(): void
    {
        $expert = Expert::factory()->verified()->create();
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
        $application = $this->submitApplication($expert);

        $this->postJson('/api/expert/kyc/submit')->assertConflict();
        $this->assertSame(2, $application->statusHistories()->count());
        $this->assertDatabaseHas('experts', ['id' => $expert->id, 'kyc_status' => 'pending']);
    }

    private function submitApplication(Expert $expert): ExpertKycApplication
    {
        $this->putJson('/api/expert/kyc', [
            'country' => 'JO',
            'language' => 'en',
            'domain' => 'technology',
            'jurisdiction' => 'Global remote services',
            'experiences' => [[
                'jobTitle' => 'Software Architect',
                'organization' => 'Example Studio',
                'current' => true,
            ]],
        ])->assertOk();

        foreach (['identity', 'cv'] as $type) {
            $this->post('/api/expert/kyc/documents', [
                'documentType' => $type,
                'file' => UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->postJson('/api/expert/kyc/submit')
            ->assertOk()
            ->assertJsonPath('data.application.status', 'submitted');

        return $expert->kycApplications()->firstOrFail();
    }
}
