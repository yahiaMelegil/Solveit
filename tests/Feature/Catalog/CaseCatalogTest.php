<?php

namespace Tests\Feature\Catalog;

use App\Models\Admin;
use App\Models\Expert;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CaseCatalogTest extends CatalogTestCase
{
    public function test_global_service_independent_of_residence_has_pinned_policy_and_audit(): void
    {
        $v = $this->entry();
        $s = $this->expertGrant($v);
        $c = $this->readyCase($v);
        $this->assertSame('ready_for_matching', $c['status']);
        $this->assertSame('PS', $c['intake']['caseCountry']);
        $this->assertSame('GLOBAL', $c['scopes'][0]['jurisdictionMode']);
        $this->assertSame($v['catalogVersion'], $c['scopes'][0]['policySnapshot']['catalogVersion']);
        $this->assertDatabaseHas('audit_events', ['action' => 'case.scope_submitted']);
        $this->assertStringNotContainsString('software', DB::table('case_intake_versions')->value('payload'));
        $this->postJson('/api/user/cases/'.$c['id'].'/submit', ['expectedVersion' => $c['version']], $this->key())->assertConflict()->assertJsonPath('code', 'CATALOG_UPGRADE_REQUIRED');
    }

    public function test_missing_supply_and_expired_scope_fail_closed(): void
    {
        $v = $this->entry();
        $c = $this->select($this->draft(), [$this->scopeInput($v)]);
        $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonPath('data.item.status', 'waiting_for_expert_supply');
        $s = $this->expertGrant($v);
        $s->forceFill(['valid_until' => today()->subDay()])->save();
        $this->postJson('/api/v2/user/cases/'.$c['id'].'/confirm', ['expectedVersion' => $c['version'], 'confirmed' => true], $this->key())->assertConflict();
    }

    public function test_version_idempotency_ownership_and_realm_isolation(): void
    {
        $c = $this->draft();
        $url = '/api/v2/user/cases/'.$c['id'];
        $key = $this->key();
        $p = ['expectedVersion' => $c['version'], 'title' => 'Changed'];
        $this->patchJson($url, $p, $key)->assertOk();
        $this->patchJson($url, $p, $key)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->patchJson($url, $p, $this->key())->assertConflict()->assertJsonPath('code', 'VERSION_CONFLICT');
        $this->patchJson($url, array_replace($p, ['title' => 'Other']), $key)->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->asAccount(User::factory()->create());
        $this->getJson($url)->assertNotFound();
        $this->postJson($url.'/submit', ['expectedVersion' => 1], $this->key())->assertNotFound();
        foreach ([Expert::factory()->create(), Admin::factory()->create()] as $actor) {
            $this->asAccount($actor, ['*']);
            $this->getJson($url)->assertForbidden();
        }
        $this->withHeader('Authorization', 'Bearer invalid');
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_schema_and_self_only_validation(): void
    {
        $this->postJson('/api/v2/user/cases', ['subjectType' => 'other'], $this->key())->assertUnprocessable();
        $v = $this->entry(['intakeSchema' => [['key' => 'teamSize', 'type' => 'number', 'required' => true, 'label' => ['ar' => 'حجم الفريق', 'en' => 'Team size']]]]);
        $this->expertGrant($v);
        $c = $this->select($this->draft(), [$this->scopeInput($v)]);
        $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonPath('data.item.reasonCodes.0', 'CASE_INCOMPLETE');
        $this->putJson('/api/v2/user/cases/'.$c['id'].'/scopes', ['expectedVersion' => $c['version'], 'scopes' => [$this->scopeInput($v, ['answers' => ['unknown' => 'secret']])]], $this->key())->assertUnprocessable();
    }

    public function test_emergency_remote_and_ambiguous_paths(): void
    {
        $v = $this->entry();
        $this->expertGrant($v);
        foreach (['immediateDanger' => 'safety_referral_required', 'requiresInPerson' => 'not_suitable_for_remote_service', 'ambiguousHighRisk' => 'needs_clarification'] as $key => $status) {
            $c = $this->select($this->draft(['answers' => array_replace(['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => false], [$key => true])]), [$this->scopeInput($v)]);
            $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonPath('data.item.status', $status);
        }
    }

    public function test_partial_submission_requires_consent_and_parent_does_not_claim_complete(): void
    {
        $v = $this->entry();
        $this->expertGrant($v);
        $other = $this->entry();
        $c = $this->select($this->draft(), [$this->scopeInput($v), $this->scopeInput($other)]);
        $c = $this->action($c, 'confirm', ['confirmed' => true]);
        $ids = [$c['scopes'][0]['id']];
        $this->postJson('/api/v2/user/cases/'.$c['id'].'/submit', ['expectedVersion' => $c['version'], 'selectedScopeIds' => $ids], $this->key())->assertConflict()->assertJsonPath('code', 'PARTIAL_CONSENT_REQUIRED');
        $c = $this->action($c, 'submit', ['selectedScopeIds' => $ids, 'partialConsent' => true]);
        $this->assertSame('waiting_for_expert_supply', $c['status']);
        $this->assertSame('ready_for_matching', $c['scopes'][0]['status']);
        $this->patchJson('/api/user/cases/'.$c['id'], ['expectedVersion' => $c['version'], 'title' => 'Mutate submitted input'], $this->key())->assertConflict();
    }

    public function test_pause_preserves_snapshot_but_block_requires_review(): void
    {
        $v = $this->entry();
        $this->expertGrant($v);
        $c = $this->readyCase($v);
        $snapshot = $c['scopes'][0]['policySnapshot'];
        $this->asAccount($this->catalogAdmin);
        $url = '/api/admin/catalog/versions/'.$v['id'];
        $impact = $this->getJson($url.'/impact')->assertOk()->json('data.item');
        $v = $this->postJson($url.'/pause', ['expectedVersion' => $v['revision'], 'status' => 'paused', 'impactToken' => $impact['impactToken'], 'reasonCode' => 'OPERATIONAL_PAUSE'], $this->key())->assertOk()->json('data.item');
        $this->asAccount($this->owner);
        $this->getJson('/api/v2/user/cases/'.$c['id'])->assertOk()->assertJsonPath('data.item.status', 'ready_for_matching')->assertJsonPath('data.item.scopes.0.policySnapshot', $snapshot);
        $this->asAccount($this->catalogAdmin);
        $impact = $this->getJson($url.'/impact')->json('data.item');
        $this->postJson($url.'/pause', ['expectedVersion' => $v['revision'], 'status' => 'blocked', 'impactToken' => $impact['impactToken'], 'reasonCode' => 'PROFESSIONAL_RISK'], $this->key())->assertOk();
        $this->asAccount($this->owner);
        $this->getJson('/api/v2/user/cases/'.$c['id'])->assertOk()->assertJsonPath('data.item.status', 'requires_review')->assertJsonPath('data.item.scopes.0.policySnapshot', $snapshot);
    }

    public function test_submit_rechecks_expert_after_confirmation(): void
    {
        $v = $this->entry();
        $s = $this->expertGrant($v);
        $c = $this->select($this->draft(), [$this->scopeInput($v)]);
        $c = $this->action($c, 'confirm', ['confirmed' => true]);
        $s->forceFill(['status' => 'suspended'])->save();
        $this->postJson('/api/v2/user/cases/'.$c['id'].'/submit', ['expectedVersion' => $c['version']], $this->key())->assertConflict()->assertJsonPath('code', 'NO_ELIGIBLE_EXPERT_COVERAGE');
    }
}
