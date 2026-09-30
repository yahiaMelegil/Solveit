<?php

namespace Tests\Feature\Privacy;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserProfileVersion;
use App\Services\Privacy\AuditWriter;
use App\Services\Privacy\ProfileManager;
use Illuminate\Support\Facades\DB;

class ProfilePrivacyTest extends PrivacyTestCase
{
    public function test_existing_user_profile_read_has_no_database_side_effects(): void
    {
        $this->getJson('/api/user/profile')->assertOk()->assertJsonPath('data.item.version', 0)->assertJsonPath('data.item.phone', null);
        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('user_profile_versions', 0);
    }

    public function test_update_keeps_auth_contract_and_immutable_encrypted_versions(): void
    {
        $this->patchJson('/api/user/profile', ['name' => 'Mariam Updated', 'phone' => '+970599123456', 'country' => 'ps',
            'language' => 'ar', 'timezone' => 'Asia/Gaza', 'expectedVersion' => 0])->assertOk()
            ->assertJsonPath('data.item.version', 1)->assertJsonPath('data.item.country', 'PS')->assertJsonPath('data.item.phoneVerified', false);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.user.name', 'Mariam Updated')
            ->assertJsonMissingPath('data.user.phone')->assertJsonMissingPath('data.user.profile');
        $this->assertDatabaseCount('user_profile_versions', 2);
        $this->assertSame('Mariam Hasan', UserProfileVersion::query()->where('version', 0)->first()->snapshot['name']);
        $this->assertStringNotContainsString('+970', DB::table('user_profiles')->value('phone'));
        $this->assertStringNotContainsString('Mariam', DB::table('user_profile_versions')->where('version', 1)->value('snapshot'));
        $this->assertStringNotContainsString('+970', AuditEvent::query()->get()->toJson());
    }

    public function test_profile_patch_rejects_stale_version_and_preserves_valid_record(): void
    {
        $this->patchJson('/api/user/profile', ['name' => 'First edit', 'expectedVersion' => 0])->assertOk();
        $this->patchJson('/api/user/profile', ['name' => 'Lost edit', 'expectedVersion' => 0])->assertConflict()->assertJsonPath('code', 'VERSION_CONFLICT');
        $this->assertSame('First edit', $this->owner->fresh()->name);
        $this->assertDatabaseCount('user_profile_versions', 2);
    }

    public function test_invalid_and_sensitive_profile_fields_are_rejected(): void
    {
        foreach ([['phone' => '123'], ['country' => 'ZZ'], ['timezone' => 'Fake/Zone'], ['language' => 'xx'],
            ['email' => 'changed@example.com'], ['user_id' => 2], ['password' => 'secret'], ['name' => '']] as $patch) {
            $this->patchJson('/api/user/profile', $patch + ['expectedVersion' => 0])->assertUnprocessable();
        }
        $this->assertDatabaseCount('user_profiles', 0);
    }

    public function test_noop_update_does_not_create_a_version(): void
    {
        $this->patchJson('/api/user/profile', ['name' => 'Mariam Hasan', 'expectedVersion' => 0])->assertOk()->assertJsonPath('data.item.version', 0);
        $this->assertDatabaseCount('user_profile_versions', 0);
    }

    public function test_profile_is_bound_to_current_token_owner(): void
    {
        $this->patchJson('/api/user/profile', ['country' => 'PS', 'expectedVersion' => 0])->assertOk();
        $other = User::factory()->create();
        $this->asAccount($other);
        $this->getJson('/api/user/profile')->assertOk()->assertJsonPath('data.item.country', null)->assertJsonPath('data.item.name', $other->name);
    }

    public function test_preferences_do_not_grant_marketing_or_recording_consent(): void
    {
        $this->patchJson('/api/user/preferences', ['contactChannels' => [], 'aiAssistanceEnabled' => true, 'recordingPreference' => true,
            'expectedVersion' => 0])->assertOk()->assertJsonPath('data.item.essentialChannels', ['email'])->assertJsonPath('data.item.aiTrainingEnabled', false);
        $this->assertDatabaseCount('user_consent_records', 0);
        $this->patchJson('/api/user/preferences', ['contactChannels' => ['sms'], 'expectedVersion' => 1])->assertUnprocessable();
        $this->patchJson('/api/user/preferences', ['contextVisibility' => 'public', 'expectedVersion' => 1])->assertUnprocessable();
        $this->patchJson('/api/user/preferences', ['recordingPreference' => false, 'expectedVersion' => 0])->assertConflict();
    }

    public function test_snapshot_and_audit_roll_back_when_audit_write_fails(): void
    {
        $this->mock(AuditWriter::class)->shouldReceive('write')->andThrow(new \RuntimeException('Synthetic audit failure'));
        try {
            app(ProfileManager::class)->update($this->owner, ['name' => 'Never commit', 'expectedVersion' => 0]);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        }
        $this->assertSame('Mariam Hasan', $this->owner->fresh()->name);
        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('user_profile_versions', 0);
    }
}
