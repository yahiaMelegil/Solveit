<?php

namespace Tests\Feature\Cases;

use App\Models\Admin;
use App\Models\CaseContextSnapshot;
use App\Models\SpecializedContext;
use App\Models\User;
use Database\Seeders\CasePermissionsSeeder;
use Spatie\Permission\Models\Role;

class CaseContextAndAdminTest extends CaseTestCase
{
    public function test_explicit_selected_snapshot_is_stable_and_detach_is_versioned(): void
    {
        $context = $this->makeContext();
        $case = $this->createCase();
        $body = ['contextId' => $context, 'contextVersion' => 1, 'selectedFactKeys' => ['goal'], 'authorizeUse' => true];
        $case = $this->mutation($case, 'context-snapshots', $body, 201);
        $snapshot = $case['contextSnapshots'][0];
        $this->assertCount(1, $snapshot['snapshot']['facts']);
        $this->assertArrayNotHasKey('title', $snapshot['snapshot']);
        $facts = $this->contextPayload()['facts'];
        $facts[0]['value'] = 'Changed source';
        $this->patchJson('/api/user/contexts/'.$context, ['expectedVersion' => 1, 'facts' => $facts])->assertOk();
        $this->assertSame('Review my store API', $this->current($case['id'])['contextSnapshots'][0]['snapshot']['facts'][0]['value']);
        $this->mutation($case, 'submit', status: 422);
        $case = $this->deleteJson('/api/user/cases/'.$case['id'].'/context-snapshots/'.$snapshot['id'], ['expectedVersion' => $case['version']], $this->key())->assertOk()->json('data.item');
        $this->assertSame([], $case['contextSnapshots']);
        $this->assertNotNull(CaseContextSnapshot::find($snapshot['id'])->detached_at);
    }

    public function test_context_foreign_version_unapproved_fields_and_general_reuse_rejected(): void
    {
        $context = $this->makeContext();
        $case = $this->createCase();
        $body = ['contextId' => $context, 'contextVersion' => 1, 'selectedFactKeys' => ['goal'], 'authorizeUse' => true];
        $this->mutation($case, 'context-snapshots', array_replace($body, ['authorizeUse' => false]), 422);
        $this->mutation($case, 'context-snapshots', array_replace($body, ['selectedFactKeys' => ['secret']]), 422);
        $this->mutation($case, 'context-snapshots', array_replace($body, ['contextVersion' => 2]), 409);
        SpecializedContext::find($context)->forceFill(['allow_case_reuse' => false])->save();
        $this->mutation($case, 'context-snapshots', $body, 409);
        SpecializedContext::find($context)->forceFill(['user_id' => User::factory()->create()->id])->save();
        $this->mutation($case, 'context-snapshots', $body, 404);
    }

    public function test_revoking_context_reuse_after_confirmation_blocks_submission(): void
    {
        $context = $this->makeContext();
        $case = $this->mutation($this->createCase(), 'context-snapshots', ['contextId' => $context, 'contextVersion' => 1, 'selectedFactKeys' => ['goal'], 'authorizeUse' => true], 201);
        $case = $this->mutation($case, 'intake-assessments');
        $case = $this->mutation($case, 'intake-confirmation', ['assessmentId' => $case['assessment']['id'], 'confirmed' => true]);
        $this->postJson('/api/user/contexts/'.$context.'/archive', ['expectedVersion' => 1], $this->key())->assertOk();
        $this->mutation($case, 'submit', status: 422);
    }

    public function test_admin_permissions_are_independent_and_metadata_only_with_revocation(): void
    {
        $case = $this->createCase();
        $admin = Admin::factory()->create();
        $this->asAccount($admin);
        $this->getJson('/api/admin/cases')->assertForbidden();
        $this->getJson('/api/admin/cases/'.$case['id'])->assertForbidden();
        $admin->givePermissionTo('cases.viewAny');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/cases')->assertOk()->assertJsonMissingPath('data.items.0.title')->assertJsonMissingPath('data.items.0.intake');
        $this->getJson('/api/admin/cases/'.$case['id'])->assertForbidden();
        $admin->givePermissionTo('cases.view');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/cases/'.$case['id'])->assertOk()->assertJsonMissingPath('data.item.intake')->assertJsonMissingPath('data.item.contextSnapshots');
        $this->assertDatabaseHas('audit_events', ['subject_type' => 'case', 'subject_id' => $case['id'], 'action' => 'case.admin_viewed']);
        $admin->revokePermissionTo('cases.view');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/cases/'.$case['id'])->assertForbidden();
        $this->postJson('/api/user/cases/'.$case['id'].'/cancel', ['expectedVersion' => 1], $this->key())->assertForbidden();
        $this->asAccount($this->owner, ['*']);
        $this->getJson('/api/admin/cases')->assertForbidden();
    }

    public function test_additive_seeder_preserves_custom_grants_and_timeline_hides_actor_ids(): void
    {
        $role = Role::create(['name' => 'custom_case_reader', 'guard_name' => 'admin']);
        $role->givePermissionTo('users.view');
        $this->seed(CasePermissionsSeeder::class);
        $this->seed(CasePermissionsSeeder::class);
        $this->assertTrue($role->hasPermissionTo('users.view'));
        $this->assertFalse($role->hasPermissionTo('cases.view'));
        $case = $this->createCase();
        $this->getJson('/api/user/cases/'.$case['id'].'/timeline?perPage=1')->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonMissingPath('data.items.0.actorId')->assertJsonMissingPath('data.items.0.actorTokenId');
    }
}
