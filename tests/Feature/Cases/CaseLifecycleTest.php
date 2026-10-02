<?php

namespace Tests\Feature\Cases;

use App\Models\Admin;
use App\Models\AuditEvent;
use App\Models\CaseIntakeVersion;
use App\Models\CaseRecord;
use App\Models\CatalogEntry;
use App\Models\Expert;
use App\Models\User;
use App\Services\Privacy\AuditWriter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class CaseLifecycleTest extends CaseTestCase
{
    public function test_create_resume_list_and_multidomain_submit(): void
    {
        $draft = $this->createCase([]);
        $this->assertSame('draft', $draft['status']);
        $this->assertSame(1, $draft['version']);
        $case = $this->patchJson('/api/user/cases/'.$draft['id'], ['expectedVersion' => 1] + array_replace($this->input(), ['domains' => ['technology', 'business']]), $this->key())->assertOk()->json('data.item');
        $this->asAccount($this->owner);
        $this->assertSame('Store API review', $this->current($case['id'])['title']);
        $this->getJson('/api/user/cases?domain=business&perPage=1&sortBy=updatedAt')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $case = $this->ready($case);
        $this->assertSame('ready_for_matching', $case['status']);
        $this->assertNotNull($case['submittedAt']);
        $this->assertSame(2, CaseIntakeVersion::query()->where('case_id', $case['id'])->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'case.status_changed', 'subject_id' => $case['id'], 'new_state' => 'ready_for_matching']);
    }

    public function test_version_conflict_and_idempotent_autosave_replay_do_not_overwrite_newer_input(): void
    {
        $case = $this->createCase();
        $key = $this->key();
        $url = '/api/v2/user/cases/'.$case['id'];
        $payload = ['expectedVersion' => 1, 'title' => 'Updated title'];
        $this->patchJson($url, $payload, $key)->assertOk()->assertJsonPath('data.item.version', 2);
        $this->patchJson($url, $payload, $key)->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('data.item.version', 2);
        $this->patchJson($url, ['expectedVersion' => 1, 'title' => 'Lost edit'], $this->key())->assertConflict()->assertJsonPath('code', 'VERSION_CONFLICT');
        $this->patchJson($url, ['expectedVersion' => 2, 'title' => 'Changed payload'], $key)->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame('Updated title', $this->current($case['id'])['title']);
    }

    public function test_create_replay_duplicate_submit_and_ready_freeze(): void
    {
        $key = $this->key();
        $case = $this->postJson('/api/user/cases', $this->input(), $key)->assertCreated()->json('data.item');
        $this->postJson('/api/user/cases', $this->input(), $key)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('cases', 1);
        $case = $this->prepareV2($case);
        $submit = $this->key();
        $url = '/api/v2/user/cases/'.$case['id'];
        $payload = ['expectedVersion' => $case['version']];
        $ready = $this->postJson($url.'/submit', $payload, $submit)->assertOk()->json('data.item');
        $this->postJson($url.'/submit', $payload, $submit)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->postJson($url.'/submit', ['expectedVersion' => $ready['version']], $this->key())->assertConflict()->assertJsonPath('code', 'INVALID_CASE_TRANSITION');
        $this->patchJson($url, ['expectedVersion' => $ready['version'], 'title' => 'Forbidden edit'], $this->key())->assertConflict();
        $this->assertSame(1, AuditEvent::query()->where('subject_id', $case['id'])->where('action', 'case.catalog_submitted')->count());
    }

    public function test_validation_allowlists_and_schema_risk_answers(): void
    {
        RateLimiter::for('cases-create', fn () => Limit::perMinute(1000)->by('validation-only'));
        foreach ([['subjectType' => 'other'], ['userId' => 55], ['user_id' => 55], ['status' => 'ready_for_matching'], ['risk' => 'safe'], ['answers' => ['unknown' => false]], ['jurisdiction' => 'ZZ'], ['language' => 'xx'], ['domains' => ['technology', 'technology']], ['primaryDomain' => 'unknown'], ['privacyChoice' => 'public'], ['schemaVersion' => 2]] as $bad) {
            $this->postJson('/api/user/cases', $bad, $this->key())->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        }
    }

    public function test_missing_submit_primary_domain_and_partial_answer_merge(): void
    {
        $case = $this->createCase([]);
        $this->mutation($case, 'submit', status: 422);
        $input = $this->input();
        $input['domains'] = ['business'];
        $case = $this->patchJson('/api/user/cases/'.$case['id'], ['expectedVersion' => 1] + $input, $this->key())->assertOk()->json('data.item');
        $this->mutation($case, 'submit', status: 422);
        $case = $this->patchJson('/api/user/cases/'.$case['id'], ['expectedVersion' => $case['version'], 'domains' => ['technology'], 'answers' => ['requiresInPerson' => true]], $this->key())->assertOk()->json('data.item');
        $this->assertFalse($case['intake']['answers']['immediateDanger']);
        $this->assertTrue($case['intake']['answers']['requiresInPerson']);
    }

    public function test_owner_isolation_and_account_type_confusion_on_all_main_routes(): void
    {
        $case = $this->createCase();
        $url = '/api/user/cases/'.$case['id'];
        $this->asAccount(User::factory()->create());
        $this->getJson($url)->assertNotFound()->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
        $this->patchJson($url, ['expectedVersion' => 1, 'user_id' => 3], $this->key())->assertNotFound();
        $this->getJson('/api/user/cases')->assertOk()->assertJsonPath('data.pagination.total', 0);
        foreach ([Expert::factory()->create(), Admin::factory()->create()] as $actor) {
            $this->asAccount($actor, ['*']);
            $this->getJson($url)->assertForbidden();
            $this->postJson('/api/user/cases', [], $this->key())->assertForbidden();
        }
        $this->asAccount($this->owner, ['user:verify-email']);
        $this->getJson($url)->assertForbidden();
        $this->asAccount(User::factory()->unverified()->create());
        $this->getJson('/api/user/cases')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer invalid');
        $this->getJson('/api/user/cases')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_incomplete_unsupported_urgent_and_ambiguous_paths_fail_closed(): void
    {
        foreach ([['jurisdiction' => 'US'], ['domains' => ['medical'], 'primaryDomain' => 'medical'], ['answers' => ['immediateDanger' => false, 'requiresInPerson' => true, 'ambiguousHighRisk' => false]]] as $change) {
            $case = $this->createCase(array_replace($this->input(), $change));
            $case = $this->mutation($case, 'intake-assessments');
            $this->assertSame('unsupported', $case['suitability']);
            $this->mutation($case, 'submit', status: 409);
        }
        $case = $this->createCase(array_replace($this->input(), ['answers' => ['immediateDanger' => true, 'requiresInPerson' => false, 'ambiguousHighRisk' => false]]));
        $case = $this->mutation($case, 'intake-assessments');
        $this->assertSame('urgent_stop', $case['suitability']);
        $this->assertSame('seek_local_emergency_assistance', $case['assessment']['nextAction']);
        $this->mutation($case, 'submit', status: 409);
        $case = $this->createCase(array_replace($this->input(), ['answers' => ['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => true]]));
        $case = $this->mutation($case, 'intake-assessments');
        $this->assertSame('stop_contact_support', $case['assessment']['nextAction']);
        $this->mutation($case, 'intake-confirmation', ['assessmentId' => $case['assessment']['id'], 'confirmed' => true], 409);
    }

    public function test_suggestions_do_not_change_domains_and_stale_confirmation_is_blocked(): void
    {
        $case = $this->createCase();
        $case = $this->mutation($case, 'intake-assessments');
        $assessment = $case['assessment']['id'];
        $this->assertSame('rules', $case['assessment']['suggestions'][0]['origin']);
        $this->assertSame(['technology'], $case['domains']);
        $case = $this->patchJson('/api/user/cases/'.$case['id'], ['expectedVersion' => $case['version'], 'desiredOutcome' => 'A changed requirement'], $this->key())->assertOk()->json('data.item');
        $this->mutation($case, 'intake-confirmation', ['assessmentId' => $assessment, 'confirmed' => true], 409);
        $this->getJson('/api/user/cases/'.$case['id'].'/clarifications')->assertOk()->assertJsonPath('data.item.isStale', true);
        $case = $this->mutation($case, 'intake-assessments');
        $case = $this->mutation($case, 'intake-confirmation', ['assessmentId' => $case['assessment']['id'], 'confirmed' => true]);
        config()->set('case_intake.rules_version', 'deterministic-2');
        $this->mutation($case, 'submit', status: 409);
    }

    public function test_cancel_allowed_states_and_terminal_replay(): void
    {
        $case = $this->ready($this->createCase());
        $key = $this->key();
        $url = '/api/user/cases/'.$case['id'].'/cancel';
        $data = ['expectedVersion' => $case['version']];
        $cancelled = $this->postJson($url, $data, $key)->assertOk()->assertJsonPath('data.item.status', 'cancelled')->json('data.item');
        $this->postJson($url, $data, $key)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->mutation($cancelled, 'cancel', status: 409);
        $this->mutation($cancelled, 'intake-assessments', status: 409);
        $this->assertDatabaseCount('cases', 1);
    }

    public function test_policy_material_update_requires_reconsent_but_marketing_is_not_required(): void
    {
        $case = $this->createCase();
        $case = $this->mutation($case, 'intake-assessments');
        $case = $this->mutation($case, 'intake-confirmation', ['assessmentId' => $case['assessment']['id'], 'confirmed' => true]);
        $policy = $this->policy('privacy')->replicate();
        $policy->forceFill(['version' => 'real-change', 'content' => 'Synthetic material change', 'content_hash' => hash('sha256', 'Synthetic material change'), 'effective_at' => now(), 'created_at' => now()])->save();
        $this->mutation($case, 'submit', status: 422);
        $this->assertSame('draft', CaseRecord::findOrFail($case['id'])->status->value);
    }

    public function test_encrypted_input_append_only_history_and_safe_audit(): void
    {
        $case = $this->createCase();
        $raw = DB::table('case_intake_versions')->where('case_id', $case['id'])->value('payload');
        $this->assertStringNotContainsString('reliability', $raw);
        $audit = AuditEvent::query()->where('subject_type', 'case')->firstOrFail();
        $this->assertStringNotContainsString('software', json_encode($audit->metadata));
        $this->expectException(\LogicException::class);
        $version = CaseIntakeVersion::firstOrFail();
        $version->payload = ['title' => 'Tamper'];
        $version->save();
    }

    public function test_related_case_suggestions_are_own_only_and_never_merge(): void
    {
        $own = $this->createCase();
        $other = CaseRecord::factory()->create(['title_fingerprint' => CaseRecord::find($own['id'])->title_fingerprint]);
        $case = $this->mutation($this->createCase(), 'intake-assessments');
        $this->assertSame([$own['id']], $case['assessment']['relatedCases']);
        $this->assertDatabaseCount('cases', 3);
    }

    public function test_bootstrap_no_country_enabled_is_fail_closed_and_rate_limit_envelope(): void
    {
        CatalogEntry::query()->update(['status' => 'paused']);
        $this->getJson('/api/user/case-intake/bootstrap')->assertOk()->assertJsonPath('data.item.operational.serviceConfigured', false);
        $case = $this->mutation($this->createCase(), 'intake-assessments');
        $this->assertSame('unsupported', $case['suitability']);
        RateLimiter::for('cases-read', fn () => Limit::perMinute(1)->by('case-test'));
        $this->getJson('/api/user/cases')->assertOk();
        $this->getJson('/api/user/cases')->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED')->assertHeader('Retry-After');
    }

    public function test_transaction_rolls_back_case_on_audit_failure(): void
    {
        $this->mock(AuditWriter::class, function ($mock): void {
            $mock->shouldReceive('write')->andThrow(new \RuntimeException('sensitive narrative must not escape'));
        });
        $this->postJson('/api/user/cases', $this->input(), $this->key())->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR')->assertDontSee('sensitive narrative');
        $this->assertDatabaseCount('cases', 0);
    }
}
