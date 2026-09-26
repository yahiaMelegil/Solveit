<?php

namespace Tests\Feature\Expert\Profile;

use App\Enums\ExpertKycApplicationStatus;
use App\Enums\ExpertKycStatus;
use App\Enums\ExpertScopeStatus;
use App\Models\Admin;
use App\Models\Expert;
use App\Models\ExpertKycApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpertProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_only_a_verified_expert_can_access_profile_workspace(): void
    {
        $this->getJson('/api/expert/profile')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(), ['user:access']);
        $this->getJson('/api/expert/profile')->assertForbidden();

        Sanctum::actingAs(Admin::factory()->create(), ['admin:access']);
        $this->getJson('/api/expert/profile')->assertForbidden();

        Sanctum::actingAs(Expert::factory()->create(), [Expert::ACCESS_ABILITY]);
        $this->getJson('/api/expert/profile')->assertForbidden();
    }

    public function test_workspace_is_prefilled_from_the_authenticated_expert_account(): void
    {
        $expert = Expert::factory()->verified()->create([
            'name' => 'Ahmad Ali',
            'email' => 'ahmad@example.com',
            'country' => 'JO',
            'language' => 'ar',
        ]);
        $this->actingAsExpert($expert);

        $this->getJson('/api/expert/profile')
            ->assertOk()
            ->assertJsonPath('data.account.name', 'Ahmad Ali')
            ->assertJsonPath('data.account.email', 'ahmad@example.com')
            ->assertJsonPath('data.account.country', 'JO')
            ->assertJsonPath('data.profile.publicLanguages.0', 'ar')
            ->assertJsonPath('data.availability.timezone', 'UTC')
            ->assertJsonPath('data.publication.canPublish', false)
            ->assertJsonPath('data.profile.isPublished', false);

        $this->assertDatabaseHas('expert_profiles', ['expert_id' => $expert->id]);
        $this->assertDatabaseHas('expert_availability_settings', ['expert_id' => $expert->id]);
    }

    public function test_expert_can_update_profile_and_minimum_availability(): void
    {
        $expert = Expert::factory()->verified()->create();
        $this->actingAsExpert($expert);

        $this->putJson('/api/expert/profile', $this->profilePayload())
            ->assertOk()
            ->assertJsonPath('data.profile.professionalTitle', 'Senior Legal Consultant')
            ->assertJsonPath('data.profile.specialties.0', 'Commercial contracts');

        $this->putJson('/api/expert/availability', $this->availabilityPayload())
            ->assertOk()
            ->assertJsonPath('data.availability.serviceModes.0', 'written_consultation')
            ->assertJsonPath('data.availability.maxActiveRequests', 4)
            ->assertJsonPath('data.availability.weeklySchedule.0.windows.0.start', '09:00');
    }

    public function test_profile_cannot_be_published_without_kyc_approval_and_an_effective_scope(): void
    {
        $expert = Expert::factory()->verified()->create();
        $this->actingAsExpert($expert);
        $this->putJson('/api/expert/profile', $this->profilePayload())->assertOk();
        $this->putJson('/api/expert/availability', $this->availabilityPayload())->assertOk();

        $this->postJson('/api/expert/profile/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['publication', 'missingRequirements'])
            ->assertJsonFragment(['kyc_approval'])
            ->assertJsonFragment(['verified_scope']);
    }

    public function test_approved_expert_can_publish_preview_and_unpublish_a_safe_public_profile(): void
    {
        $expert = $this->approvedExpertWithScope();
        $this->actingAsExpert($expert);
        $this->putJson('/api/expert/profile', $this->profilePayload())->assertOk();
        $this->putJson('/api/expert/availability', $this->availabilityPayload())->assertOk();

        $published = $this->postJson('/api/expert/profile/publish')
            ->assertOk()
            ->assertJsonPath('data.publication.canPublish', true)
            ->assertJsonPath('data.publication.publiclyVisible', true)
            ->assertJsonPath('data.verifiedScopes.0.isEffective', true);

        $slug = $published->json('data.profile.slug');

        $this->getJson('/api/expert/profile/preview')
            ->assertOk()
            ->assertJsonPath('data.expert.slug', $slug)
            ->assertJsonMissingPath('data.expert.email')
            ->assertJsonMissingPath('data.expert.availability.blackoutDates');

        $this->getJson('/api/experts/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.expert.name', $expert->name)
            ->assertJsonPath('data.expert.verification.status', 'verified')
            ->assertJsonPath('data.expert.verification.scopes.0.domain', 'legal')
            ->assertJsonMissingPath('data.expert.email')
            ->assertJsonMissingPath('data.expert.avatarPath');

        $this->postJson('/api/expert/profile/unpublish')->assertOk();
        $this->getJson('/api/experts/'.$slug)->assertNotFound();
    }

    public function test_expired_scope_does_not_allow_publication(): void
    {
        $expert = $this->approvedExpertWithScope(now()->subDay()->toDateString());
        $this->actingAsExpert($expert);
        $this->putJson('/api/expert/profile', $this->profilePayload())->assertOk();
        $this->putJson('/api/expert/availability', $this->availabilityPayload())->assertOk();

        $this->postJson('/api/expert/profile/publish')
            ->assertUnprocessable()
            ->assertJsonFragment(['verified_scope']);
    }

    public function test_expert_can_upload_and_remove_avatar_without_exposing_storage_path(): void
    {
        $expert = Expert::factory()->verified()->create();
        $this->actingAsExpert($expert);

        $response = $this->post('/api/expert/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('portrait.jpg', 600, 600),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonMissingPath('data.profile.avatarPath');

        $profile = $expert->profile()->firstOrFail();
        Storage::disk('public')->assertExists($profile->avatar_path);
        $this->assertNotNull($response->json('data.profile.avatarUrl'));

        $this->deleteJson('/api/expert/profile/avatar')->assertOk()
            ->assertJsonPath('data.profile.avatarUrl', null);
        Storage::disk('public')->assertMissing($profile->avatar_path);
    }

    public function test_invalid_availability_window_is_rejected(): void
    {
        $expert = Expert::factory()->verified()->create();
        $this->actingAsExpert($expert);
        $payload = $this->availabilityPayload();
        $payload['weeklySchedule'][0]['windows'][0] = ['start' => '17:00', 'end' => '09:00'];

        $this->putJson('/api/expert/availability', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weeklySchedule.0.windows.0.end');
    }

    private function actingAsExpert(Expert $expert): void
    {
        Sanctum::actingAs($expert, [Expert::ACCESS_ABILITY]);
    }

    private function approvedExpertWithScope(?string $validUntil = null): Expert
    {
        $expert = Expert::factory()->verified()->create(['kyc_status' => ExpertKycStatus::Approved]);
        $application = ExpertKycApplication::factory()->create([
            'expert_id' => $expert->id,
            'status' => ExpertKycApplicationStatus::Verified,
            'domain' => 'legal',
            'jurisdiction' => 'Jordan',
        ]);
        $expert->verifiedScopes()->create([
            'kyc_application_id' => $application->id,
            'domain' => 'legal',
            'jurisdiction' => 'Jordan',
            'role' => 'Legal consultant',
            'service_types' => ['written_consultation', 'document_review'],
            'languages' => ['ar', 'en'],
            'status' => ExpertScopeStatus::Active,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => $validUntil,
        ]);

        return $expert;
    }

    private function profilePayload(): array
    {
        return [
            'professionalTitle' => 'Senior Legal Consultant',
            'bio' => str_repeat('Experienced legal consultant supporting complex commercial matters. ', 2),
            'yearsExperience' => 12,
            'specialties' => ['Commercial contracts', 'Corporate governance'],
            'publicLanguages' => ['ar', 'en'],
        ];
    }

    private function availabilityPayload(): array
    {
        return [
            'timezone' => 'Asia/Amman',
            'serviceModes' => ['written_consultation', 'document_review'],
            'weeklySchedule' => [
                ['day' => 'mon', 'enabled' => true, 'windows' => [['start' => '09:00', 'end' => '14:00']]],
                ['day' => 'tue', 'enabled' => false, 'windows' => []],
                ['day' => 'wed', 'enabled' => false, 'windows' => []],
                ['day' => 'thu', 'enabled' => false, 'windows' => []],
                ['day' => 'fri', 'enabled' => false, 'windows' => []],
                ['day' => 'sat', 'enabled' => false, 'windows' => []],
                ['day' => 'sun', 'enabled' => false, 'windows' => []],
            ],
            'blackoutDates' => [],
            'maxActiveRequests' => 4,
            'responseTimeHours' => 24,
            'acceptingNewRequests' => true,
        ];
    }
}
