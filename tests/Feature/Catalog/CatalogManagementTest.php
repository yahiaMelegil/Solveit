<?php

namespace Tests\Feature\Catalog;

use App\Models\Admin;
use App\Models\CaseRecord;
use App\Models\CatalogNode;
use App\Models\CatalogVersion;
use App\Models\ExpertCatalogGrant;
use Database\Seeders\ServiceCatalogSeeder;

class CatalogManagementTest extends CatalogTestCase
{
    public function test_permissions_and_revocation_on_same_token_are_enforced(): void
    {
        $a = Admin::factory()->create();
        $a->givePermissionTo('catalog.view');
        $this->asAccount($a);
        $this->getJson('/api/admin/catalog/entries')->assertOk();
        $this->postJson('/api/admin/catalog/entries', ['code' => 'denied', 'policy' => $this->policyData()], $this->key())->assertForbidden();
        $a->revokePermissionTo('catalog.view');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/catalog/entries')->assertForbidden();
    }

    public function test_policy_allowlists_and_unknown_schema_fail_closed(): void
    {
        $this->asAccount($this->catalogAdmin);
        foreach ([['specialty' => 'invented'], ['ownerId' => 9], ['requiresVerifiedScope' => false], ['intakeSchema' => [['key' => 'x', 'type' => 'php', 'required' => true, 'label' => ['ar' => 'س', 'en' => 'x']]]], ['requiredConsents' => ['terms', 'marketing']], ['regulated' => true]] as $change) {
            $this->postJson('/api/admin/catalog/entries', ['code' => 'bad_'.bin2hex(random_bytes(4)), 'policy' => $this->policyData($change)], $this->key())->assertUnprocessable();
        }
    }

    public function test_draft_seed_is_additive_and_does_not_enable_services(): void
    {
        $this->getJson('/api/catalog/entries')->assertOk()->assertJsonPath('data.pagination.total', 0);
        $v = CatalogVersion::first();
        $v->entry->forceFill(['status' => 'paused'])->save();
        $before = CatalogVersion::count();
        $this->seed(ServiceCatalogSeeder::class);
        $this->assertSame($before, CatalogVersion::count());
        $this->assertSame('paused', $v->entry->fresh()->status);
    }

    public function test_review_is_required_and_impact_must_be_fresh(): void
    {
        $this->asAccount($this->catalogAdmin);
        $v = $this->postJson('/api/admin/catalog/entries', ['code' => 'review_gate', 'policy' => $this->policyData()], $this->key())->assertCreated()->json('data.item');
        $url = '/api/admin/catalog/versions/'.$v['id'];
        $impact = $this->getJson($url.'/impact')->json('data.item');
        $this->postJson($url.'/publish', ['expectedVersion' => 1, 'status' => 'enabled', 'impactToken' => $impact['impactToken']], $this->key())->assertConflict();
        $this->postJson($url.'/review', ['expectedVersion' => 1, 'approved' => true, 'reasonCode' => 'REVIEWED'], $this->key())->assertOk();
        $this->postJson($url.'/publish', ['expectedVersion' => 2, 'status' => 'enabled', 'impactToken' => $impact['impactToken']], $this->key())->assertConflict()->assertJsonPath('code', 'IMPACT_CHANGED');
    }

    public function test_explicit_grant_is_required_and_revocation_flags_ready_case(): void
    {
        $v = $this->entry();
        $s = $this->expertGrant($v);
        $c = $this->readyCase($v);
        $g = ExpertCatalogGrant::where('scope_id', $s->id)->firstOrFail();
        $this->asAccount($this->catalogAdmin);
        $this->postJson('/api/admin/catalog/grants/'.$g->id.'/revoke', ['reasonCode' => 'LICENSE_REVIEW'], $this->key())->assertOk();
        $this->assertSame('requires_review', CaseRecord::find($c['id'])->readiness_status);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalog.expert_revoked']);
    }

    public function test_country_expansion_and_multi_country_coverage_are_database_driven(): void
    {
        CatalogNode::whereIn('code', ['JO', 'AE', 'JO-NATIONAL', 'AE-FEDERAL'])->update(['status' => 'enabled']);
        $v = $this->entry(['jurisdictionMode' => 'MULTI_COUNTRY', 'jurisdictionCodes' => ['JO-NATIONAL', 'AE-FEDERAL']]);
        $this->expertGrant($v, 'JO-NATIONAL', 'JO');
        $c = $this->select($this->draft(), [$this->scopeInput($v)]);
        $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonPath('data.item.status', 'waiting_for_expert_supply');
        $this->expertGrant($v, 'AE-FEDERAL', 'AE');
        $c = $this->action($c, 'confirm', ['confirmed' => true]);
        $c = $this->action($c, 'submit');
        $this->assertSame('ready_for_matching', $c['status']);
    }

    public function test_regulated_creator_reviewer_publisher_are_distinct_even_superadmins(): void
    {
        $legal = CatalogNode::where('kind', 'domain')->where('code', 'legal')->firstOrFail();
        $legal->forceFill(['status' => 'enabled'])->save();
        $specialty = new CatalogNode;
        $specialty->forceFill(['kind' => 'specialty', 'code' => 'contracts', 'parent_id' => $legal->id, 'labels' => ['ar' => 'العقود', 'en' => 'Contracts'], 'status' => 'enabled'])->save();
        $service = new CatalogNode;
        $service->forceFill(['kind' => 'service', 'code' => 'contract_review', 'parent_id' => $specialty->id, 'labels' => ['ar' => 'مراجعة عقد', 'en' => 'Contract review'], 'status' => 'enabled'])->save();
        CatalogNode::whereIn('code', ['AE', 'AE-DIFC'])->update(['status' => 'enabled']);
        $p = $this->policyData(['domain' => 'legal', 'specialty' => 'contracts', 'serviceType' => 'contract_review', 'regulated' => true, 'requiresProfessionalLicense' => true, 'requiredEvidenceType' => 'credential', 'jurisdictionMode' => 'COUNTRY_SPECIFIC', 'jurisdictionCodes' => ['AE-DIFC']]);
        $this->asAccount($this->catalogAdmin);
        $v = $this->postJson('/api/admin/catalog/entries', ['code' => 'legal_difc', 'policy' => $p], $this->key())->assertCreated()->json('data.item');
        $url = '/api/admin/catalog/versions/'.$v['id'];
        $review = ['expectedVersion' => 1, 'approved' => true, 'reasonCode' => 'LICENSE_POLICY_REVIEW'];
        $this->postJson($url.'/review', $review, $this->key())->assertForbidden();
        $reviewer = Admin::factory()->create();
        $reviewer->assignRole('super_admin');
        $this->asAccount($reviewer);
        $this->postJson($url.'/review', $review, $this->key())->assertOk();
        $impact = $this->getJson($url.'/impact')->json('data.item');
        $publish = ['expectedVersion' => 2, 'status' => 'enabled', 'impactToken' => $impact['impactToken']];
        $this->postJson($url.'/publish', $publish, $this->key())->assertForbidden();
        $this->asAccount($this->catalogAdmin);
        $this->postJson($url.'/publish', $publish, $this->key())->assertForbidden();
        $publisher = Admin::factory()->create();
        $publisher->assignRole('super_admin');
        $this->asAccount($publisher);
        $this->postJson($url.'/publish', $publish, $this->key())->assertOk();
    }

    public function test_admin_cases_are_metadata_only_and_permissions_are_independent(): void
    {
        $c = $this->draft();
        $a = Admin::factory()->create();
        $a->givePermissionTo('cases.viewAny');
        $this->asAccount($a);
        $this->getJson('/api/v2/admin/cases')->assertOk()->assertDontSee('Software review')->assertDontSee('architecture');
        $this->getJson('/api/v2/admin/cases/'.$c['id'])->assertForbidden();
        $a->givePermissionTo('cases.view');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v2/admin/cases/'.$c['id'])->assertOk()->assertDontSee('problemDescription')->assertDontSee('policySnapshot');
    }

    public function test_known_unsafe_description_cannot_hide_behind_safe_domain(): void
    {
        $v = $this->entry();
        $this->expertGrant($v);
        $c = $this->select($this->draft(['problemDescription' => 'Please prescribe medication for me']), [$this->scopeInput($v)]);
        $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonPath('data.item.status', 'safety_referral_required');
    }
}
