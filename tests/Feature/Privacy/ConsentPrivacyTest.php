<?php

namespace Tests\Feature\Privacy;

use App\Models\AuditEvent;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Models\UserConsentRecord;

class ConsentPrivacyTest extends PrivacyTestCase
{
    public function test_consent_records_exact_policy_version_hash_and_server_timestamp(): void
    {
        $id = $this->consent();
        $record = UserConsentRecord::findOrFail($id);
        $this->assertSame($this->policy()->id, $record->policy_version_id);
        $this->assertNotNull($record->decided_at);
        $this->getJson('/api/user/consents/history')->assertOk()->assertJsonPath('data.items.0.policyHash', $this->policy()->content_hash);
        $this->getJson('/api/user/consents')->assertOk()->assertJsonPath('data.items.2.effective', true);
    }

    public function test_withdrawal_appends_history_and_does_not_disable_essential_communications(): void
    {
        $id = $this->consent();
        $this->postJson('/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $this->policy()->id,
            'decision' => 'withdrawn', 'previousRecordId' => $id], $this->key())->assertCreated();
        $this->assertDatabaseCount('user_consent_records', 2);
        $this->assertSame('granted', UserConsentRecord::find($id)->decision->value);
        $this->getJson('/api/user/consents')->assertJsonPath('data.items.2.effective', false)->assertJsonPath('data.items.2.decision', 'withdrawn');
        $this->getJson('/api/user/preferences')->assertJsonPath('data.item.essentialChannels', ['email']);
    }

    public function test_material_policy_update_requires_reconsent_but_allows_old_grant_withdrawal(): void
    {
        $id = $this->consent();
        $old = $this->policy();
        $new = $old->replicate();
        $new->forceFill(['version' => 'demo-2', 'effective_at' => now(), 'created_at' => now(), 'content' => 'New synthetic policy', 'content_hash' => hash('sha256', 'New synthetic policy')])->save();
        $this->getJson('/api/user/consents')->assertJsonPath('data.items.2.requiresReconsent', true)->assertJsonPath('data.items.2.effective', false);
        $this->postJson('/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $old->id, 'decision' => 'granted'], $this->key())
            ->assertConflict()->assertJsonPath('code', 'POLICY_VERSION_CHANGED');
        $this->postJson('/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $old->id,
            'decision' => 'withdrawn', 'previousRecordId' => $id], $this->key())->assertCreated();
    }

    public function test_unknown_policy_purpose_and_forged_metadata_are_rejected(): void
    {
        $this->postJson('/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $this->policy('terms')->id, 'decision' => 'granted'], $this->key())->assertUnprocessable();
        $this->postJson('/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $this->policy()->id, 'decision' => 'granted', 'decidedAt' => '2001-01-01'], $this->key())->assertUnprocessable();
        $this->assertDatabaseCount('user_consent_records', 0);
    }

    public function test_cannot_withdraw_another_users_consent_or_required_terms(): void
    {
        $id = $this->consent();
        $this->asAccount(User::factory()->create());
        $this->postJson('/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $this->policy()->id,
            'decision' => 'withdrawn', 'previousRecordId' => $id], $this->key())->assertConflict();
        $terms = $this->consent('terms');
        $this->postJson('/api/user/consents', ['purpose' => 'terms', 'policyVersionId' => $this->policy('terms')->id,
            'decision' => 'withdrawn', 'previousRecordId' => $terms], $this->key())->assertUnprocessable();
    }

    public function test_duplicate_consent_has_one_record_and_one_audit_event(): void
    {
        $this->consent();
        $this->consentWithStatus200();
        $this->assertDatabaseCount('user_consent_records', 1);
        $this->assertSame(1, AuditEvent::query()->where('action', 'user.consent_recorded')->count());
    }

    private function consentWithStatus200(): void
    {
        $this->postJson('/api/user/consents', ['purpose' => 'marketing', 'policyVersionId' => $this->policy()->id, 'decision' => 'granted'], $this->key())->assertOk();
    }

    public function test_policy_and_consent_history_are_application_append_only(): void
    {
        $id = $this->consent();
        try {
            UserConsentRecord::find($id)->forceFill(['decision' => 'declined'])->save();
            $this->fail('Expected append-only protection');
        } catch (\LogicException) {
            $this->assertSame('granted', UserConsentRecord::find($id)->decision->value);
        }
        try {
            $this->policy()->forceFill(['content' => 'Changed'])->save();
            $this->fail('Expected append-only protection');
        } catch (\LogicException) {
            $this->assertNotSame('Changed', $this->policy()->content);
        }
    }

    public function test_empty_policy_registry_never_creates_implicit_consents(): void
    {
        PolicyVersion::query()->delete();
        $this->getJson('/api/user/policies')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/user/consents')->assertOk()->assertJsonPath('data.items.0.decision', 'not_recorded');
        $this->assertDatabaseCount('user_consent_records', 0);
    }
}
