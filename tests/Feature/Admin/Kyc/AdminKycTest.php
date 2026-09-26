<?php

namespace Tests\Feature\Admin\Kyc;

use App\Enums\AdminRole;
use App\Enums\ExpertKycStatus;
use App\Models\Admin;
use App\Models\Expert;
use App\Models\ExpertKycApplication;
use App\Models\User;
use App\Notifications\Expert\KycReviewStatusNotification;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminKycTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        Storage::fake('kyc');
    }

    public function test_only_an_administrator_can_access_admin_kyc_routes(): void
    {
        $this->getJson('/api/admin/kyc/applications')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(), ['user:access']);
        $this->getJson('/api/admin/kyc/applications')->assertForbidden();

        Sanctum::actingAs(Expert::factory()->verified()->create(), ['expert:access']);
        $this->getJson('/api/admin/kyc/applications')->assertForbidden();

        Sanctum::actingAs(Admin::factory()->create(), [Admin::ACCESS_ABILITY]);
        $this->getJson('/api/admin/kyc/applications')->assertForbidden();
    }

    public function test_admin_list_supports_search_status_filter_and_pagination_without_paths(): void
    {
        $first = $this->submittedApplication('Legal Expert', 'legal');
        $this->submittedApplication('Finance Expert', 'finance');

        Sanctum::actingAs($this->kycReviewer(), [Admin::ACCESS_ABILITY]);
        $response = $this->getJson('/api/admin/kyc/applications?search=Legal&status=submitted&perPage=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data.applications')
            ->assertJsonPath('data.applications.0.id', $first->id)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonMissingPath('data.applications.0.path');
    }

    public function test_admin_can_review_all_documents_and_approve_a_complete_application(): void
    {
        Notification::fake();
        $application = $this->submittedApplication();
        $admin = $this->kycReviewer();
        Sanctum::actingAs($admin, [Admin::ACCESS_ABILITY]);

        $this->postJson("/api/admin/kyc/applications/{$application->id}/start-review")
            ->assertOk()
            ->assertJsonPath('data.application.status', 'under_review');

        foreach ($application->documents as $document) {
            $this->putJson("/api/admin/kyc/applications/{$application->id}/documents/{$document->id}/review", [
                'reviewed' => true,
            ])->assertOk();
        }

        $this->postJson("/api/admin/kyc/applications/{$application->id}/approve", [
            'scopes' => [[
                'domain' => 'legal',
                'jurisdiction' => 'Jordan',
                'role' => 'Legal consultant',
                'serviceTypes' => ['written_consultation', 'document_review'],
                'languages' => ['ar', 'en'],
                'validUntil' => now()->addYear()->toDateString(),
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.application.status', 'verified')
            ->assertJsonPath('data.application.verifiedScopes.0.role', 'Legal consultant')
            ->assertJsonPath('data.application.verifiedScopes.0.isEffective', true);

        $this->assertSame(ExpertKycStatus::Approved, $application->expert->refresh()->kyc_status);
        $this->assertDatabaseHas('expert_kyc_applications', [
            'id' => $application->id,
            'reviewed_by_admin_id' => $admin->id,
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('expert_verified_scopes', [
            'expert_id' => $application->expert_id,
            'kyc_application_id' => $application->id,
            'verified_by_admin_id' => $admin->id,
            'domain' => 'legal',
            'jurisdiction' => 'Jordan',
            'role' => 'Legal consultant',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('expert_kyc_status_histories', 4);
        Notification::assertSentTo(
            $application->expert,
            KycReviewStatusNotification::class,
            fn (KycReviewStatusNotification $notification): bool => $notification->status->value === 'verified',
        );
    }

    public function test_admin_cannot_approve_before_every_document_is_reviewed(): void
    {
        $application = $this->submittedApplication();
        Sanctum::actingAs($this->kycReviewer(), [Admin::ACCESS_ABILITY]);
        $this->postJson("/api/admin/kyc/applications/{$application->id}/start-review")->assertOk();

        $this->postJson("/api/admin/kyc/applications/{$application->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents');
    }

    public function test_reject_and_request_information_require_a_reason(): void
    {
        Notification::fake();
        $application = $this->submittedApplication();
        Sanctum::actingAs($this->kycReviewer(), [Admin::ACCESS_ABILITY]);
        $this->postJson("/api/admin/kyc/applications/{$application->id}/start-review")->assertOk();

        $this->postJson("/api/admin/kyc/applications/{$application->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/admin/kyc/applications/{$application->id}/request-information", [
            'reason' => 'Please upload a clearer identity document.',
            'requestedChanges' => [[
                'section' => 'identity_scope',
                'field' => 'identityEvidence',
                'documentId' => $application->documents->first()->id,
                'message' => 'Upload a clear, uncropped identity image.',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.application.status', 'needs_information')
            ->assertJsonPath('data.application.reviewFeedback.reason', 'Please upload a clearer identity document.')
            ->assertJsonPath('data.application.reviewFeedback.requestedChanges.0.section', 'identity_scope')
            ->assertJsonPath('data.application.reviewFeedback.requestedChanges.0.field', 'identityEvidence');

        Notification::assertSentTo(
            $application->expert,
            KycReviewStatusNotification::class,
            fn (KycReviewStatusNotification $notification): bool => $notification->reference === $application->reference,
        );

        Sanctum::actingAs($application->expert, [Expert::ACCESS_ABILITY]);
        $this->getJson('/api/expert/kyc')
            ->assertOk()
            ->assertJsonPath('data.application.status', 'needs_information')
            ->assertJsonPath('data.application.reviewFeedback.requestedChanges.0.message', 'Upload a clear, uncropped identity image.');

        $this->putJson('/api/expert/kyc', ['jurisdiction' => 'Jordan'])->assertOk()
            ->assertJsonPath('data.application.status', 'draft')
            ->assertJsonPath('data.application.reviewFeedback.reason', 'Please upload a clearer identity document.')
            ->assertJsonPath('data.application.revisionSource.sourceApplicationId', $application->id);

        $this->assertDatabaseHas('expert_kyc_applications', [
            'expert_id' => $application->expert_id,
            'attempt_number' => 2,
            'source_application_id' => $application->id,
            'status' => 'draft',
        ]);
    }

    public function test_requested_change_document_must_belong_to_the_application(): void
    {
        $first = $this->submittedApplication('First Expert');
        $second = $this->submittedApplication('Second Expert');
        Sanctum::actingAs($this->kycReviewer(), [Admin::ACCESS_ABILITY]);
        $this->postJson("/api/admin/kyc/applications/{$first->id}/start-review")->assertOk();

        $this->postJson("/api/admin/kyc/applications/{$first->id}/request-information", [
            'reason' => 'A document needs to be replaced.',
            'requestedChanges' => [[
                'section' => 'identity_scope',
                'documentId' => $second->documents->first()->id,
                'message' => 'Replace this document.',
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('requestedChanges.0.documentId');
    }

    public function test_repeated_or_conflicting_admin_decisions_are_rejected(): void
    {
        $application = $this->submittedApplication();
        Sanctum::actingAs($this->kycReviewer(), [Admin::ACCESS_ABILITY]);
        $this->postJson("/api/admin/kyc/applications/{$application->id}/start-review")->assertOk();
        $this->postJson("/api/admin/kyc/applications/{$application->id}/reject", [
            'reason' => 'The submitted identity evidence is invalid.',
        ])->assertOk();

        $this->postJson("/api/admin/kyc/applications/{$application->id}/approve")
            ->assertStatus(409);
    }

    public function test_admin_cannot_review_a_document_from_a_different_application(): void
    {
        $first = $this->submittedApplication('First Expert');
        $second = $this->submittedApplication('Second Expert');
        Sanctum::actingAs($this->kycReviewer(), [Admin::ACCESS_ABILITY]);
        $this->postJson("/api/admin/kyc/applications/{$first->id}/start-review")->assertOk();

        $this->putJson("/api/admin/kyc/applications/{$first->id}/documents/{$second->documents->first()->id}/review", [
            'reviewed' => true,
        ])->assertNotFound();
    }

    public function test_draft_application_is_not_visible_to_admin(): void
    {
        $application = ExpertKycApplication::factory()->create();
        Sanctum::actingAs($this->kycReviewer(), [Admin::ACCESS_ABILITY]);

        $this->getJson("/api/admin/kyc/applications/{$application->id}")->assertNotFound();
    }

    private function submittedApplication(string $name = 'Ahmad Ali', string $domain = 'legal'): ExpertKycApplication
    {
        $expert = Expert::factory()->verified()->create(['name' => $name, 'domain' => $domain]);
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);

        $this->putJson('/api/expert/kyc', [
            'fullName' => $name,
            'country' => 'JO',
            'language' => 'ar',
            'domain' => $domain,
            'jurisdiction' => 'Jordan',
            'experiences' => [[
                'jobTitle' => 'Senior Consultant',
                'organization' => 'Example Office',
                'current' => true,
            ]],
            'qualifications' => [],
            'credentials' => [],
        ])->assertOk();

        foreach (['identity', 'cv'] as $type) {
            $this->post('/api/expert/kyc/documents', [
                'documentType' => $type,
                'file' => UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->postJson('/api/expert/kyc/submit')->assertOk();

        return ExpertKycApplication::query()
            ->where('expert_id', $expert->id)
            ->with(['expert', 'documents'])
            ->firstOrFail();
    }

    private function kycReviewer(): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::KycReviewer->value);

        return $admin;
    }
}
