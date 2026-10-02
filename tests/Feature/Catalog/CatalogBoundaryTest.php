<?php

namespace Tests\Feature\Catalog;

use App\Models\CaseServiceScope;
use App\Models\CatalogEntry;
use App\Models\CatalogVersion;
use Illuminate\Support\Facades\DB;

class CatalogBoundaryTest extends CatalogTestCase
{
    public function test_same_key_submit_replays_and_cancellation_is_terminal(): void
    {
        $v = $this->entry();
        $this->expertGrant($v);
        $c = $this->select($this->draft(), [$this->scopeInput($v)]);
        $c = $this->action($c, 'confirm', ['confirmed' => true]);
        $key = $this->key();
        $url = '/api/v2/user/cases/'.$c['id'];
        $data = ['expectedVersion' => $c['version']];
        $first = $this->postJson($url.'/submit', $data, $key)->assertOk();
        $ready = $first->json('data.item');
        $replay = $this->postJson($url.'/submit', $data, $key)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->getContent(), $replay->getContent());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'case.catalog_submitted')->count());
        $cancel = $this->action($ready, 'cancel');
        $this->assertSame('cancelled', $cancel['scopes'][0]['status']);
        $this->postJson($url.'/confirm', ['expectedVersion' => $cancel['version'], 'confirmed' => true], $this->key())->assertConflict();
    }

    public function test_pilot_and_expired_policies_never_produce_ready(): void
    {
        $v = $this->entry(['effectiveUntil' => now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z')]);
        $this->expertGrant($v);
        $c = $this->select($this->draft(), [$this->scopeInput($v)]);
        $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonFragment(['reasonCodes' => ['SERVICE_NOT_LAUNCHED']]);
    }

    public function test_new_version_never_rewrites_pinned_scope_and_old_selection_becomes_stale(): void
    {
        $v = $this->entry();
        $this->expertGrant($v);
        $ready = $this->readyCase($v);
        $draft = $this->select($this->draft(), [$this->scopeInput($v)]);
        $this->asAccount($this->catalogAdmin);
        $e = CatalogEntry::find($v['catalogEntryId']);
        $next = $this->postJson('/api/admin/catalog/entries/'.$e->id.'/versions', ['expectedVersion' => $e->version, 'policy' => $this->policyData(['minimumExperts' => 2, 'maximumExperts' => 2])], $this->key())->assertCreated()->json('data.item');
        $url = '/api/admin/catalog/versions/'.$next['id'];
        $next = $this->postJson($url.'/review', ['expectedVersion' => 1, 'approved' => true, 'reasonCode' => 'POLICY_REVIEW'], $this->key())->assertOk()->json('data.item');
        $impact = $this->getJson($url.'/impact')->json('data.item');
        $this->postJson($url.'/publish', ['expectedVersion' => $next['revision'], 'status' => 'enabled', 'impactToken' => $impact['impactToken']], $this->key())->assertOk();
        $this->asAccount($this->owner);
        $this->getJson('/api/v2/user/cases/'.$ready['id'])->assertOk()->assertJsonPath('data.item.scopes.0.catalogVersion', 1);
        $response = $this->getJson('/api/v2/user/cases/'.$draft['id'].'/readiness')->assertOk();
        $this->assertContains('CATALOG_VERSION_CHANGED', $response->json('data.item.reasonCodes'));
    }

    public function test_reviewed_policy_is_immutable(): void
    {
        $v = $this->entry();
        $row = CatalogVersion::find($v['id']);
        $row->policy = $this->policyData(['minimumExperts' => 2, 'maximumExperts' => 2]);
        $this->expectException(\LogicException::class);
        $row->save();
    }

    public function test_submitted_snapshot_is_immutable(): void
    {
        $v = $this->entry();
        $this->expertGrant($v);
        $c = $this->readyCase($v);
        $s = CaseServiceScope::find($c['scopes'][0]['id']);
        $s->answers = ['tamper' => true];
        $this->expectException(\LogicException::class);
        $s->save();
    }

    public function test_zero_scope_draft_and_missing_documents_and_consent_are_blocked(): void
    {
        $c = $this->draft();
        $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonPath('data.item.reasonCodes.0', 'CASE_INCOMPLETE');
        $v = $this->entry(['requiredDocuments' => ['project_brief']]);
        $this->expertGrant($v);
        $c = $this->select($c, [$this->scopeInput($v)]);
        $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->assertJsonPath('data.item.reasonCodes.0', 'MISSING_REQUIRED_DOCUMENTS');
        $policy = $this->policy('privacy')->replicate();
        $policy->forceFill(['version' => 'new-required', 'content_hash' => hash('sha256', 'new'), 'effective_at' => now(), 'created_at' => now(), 'requires_reconsent' => true])->save();
        $result = $this->getJson('/api/v2/user/cases/'.$c['id'].'/readiness')->assertOk()->json('data.item');
        $this->assertContains('MISSING_REQUIRED_CONSENT', $result['reasonCodes']);
    }

    public function test_catalog_node_activation_requires_publish_and_country_can_be_added_after_launch(): void
    {
        $this->entry();
        $this->asAccount($this->catalogAdmin);
        $country = $this->postJson('/api/admin/catalog/nodes', ['kind' => 'country', 'code' => 'CA', 'labels' => ['ar' => 'كندا', 'en' => 'Canada']], $this->key())->assertCreated()->json('data.item');
        $this->patchJson('/api/admin/catalog/nodes/'.$country['id'], ['expectedVersion' => 1, 'status' => 'enabled'], $this->key())->assertOk();
        $this->getJson('/api/catalog/countries')->assertOk()->assertJsonFragment(['code' => 'CA']);
        $this->assertDatabaseMissing('catalog_entries', ['code' => 'ca_all_services']);
    }

    public function test_scope_reselection_serializes_a_list_and_choices_are_distinct_per_field(): void
    {
        $field = ['key' => 'firstChoice', 'type' => 'choice', 'required' => true, 'label' => ['ar' => 'اختيار', 'en' => 'Choice'], 'options' => ['yes', 'no']];
        $other = array_replace($field, ['key' => 'secondChoice']);
        $v = $this->entry(['intakeSchema' => [$field, $other]]);
        $this->expertGrant($v);
        $input = array_replace($this->scopeInput($v), ['answers' => ['firstChoice' => 'yes', 'secondChoice' => 'no']]);
        $c = $this->select($this->draft(), [$input]);
        $c = $this->select($c, [$input]);
        $this->assertTrue(array_is_list($c['scopes']));
        $this->assertCount(1, $c['scopes']);
        $c = $this->action($c, 'confirm', ['confirmed' => true]);
        $c = $this->action($c, 'submit');
        $this->assertSame([], $c['scopes'][0]['policySnapshot']['contextSnapshotIds']);
    }
}
