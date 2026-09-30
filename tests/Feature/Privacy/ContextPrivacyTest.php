<?php

namespace Tests\Feature\Privacy;

use App\Models\SpecializedContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContextPrivacyTest extends PrivacyTestCase
{
    public function test_create_show_list_and_encrypted_storage(): void
    {
        $id = $this->makeContext();
        $this->getJson('/api/user/contexts/'.$id)->assertOk()->assertJsonPath('data.item.facts.0.source', 'user_reported')->assertJsonPath('data.item.canUseInFutureCase', true);
        $this->getJson('/api/user/contexts?perPage=1&domain=technology')->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonMissingPath('data.items.0.facts');
        $this->assertStringNotContainsString('Review my store API', DB::table('specialized_context_versions')->value('payload'));
    }

    public function test_conflicting_facts_keep_both_versions_and_require_clarification(): void
    {
        $id = $this->makeContext();
        $facts = $this->contextPayload()['facts'];
        $facts[0]['value'] = 'Replace the store architecture';
        $this->patchJson('/api/user/contexts/'.$id, ['facts' => $facts, 'expectedVersion' => 1])->assertOk()
            ->assertJsonPath('data.item.conflictStatus', 'unresolved')->assertJsonPath('data.item.canUseInFutureCase', false);
        $this->patchJson('/api/user/contexts/'.$id, ['title' => 'Another title', 'expectedVersion' => 2])->assertOk()->assertJsonPath('data.item.conflictStatus', 'unresolved');
        $this->patchJson('/api/user/contexts/'.$id, ['clarification' => 'The earlier goal was entered incorrectly.', 'expectedVersion' => 3])->assertOk()
            ->assertJsonPath('data.item.conflictStatus', 'clarified')->assertJsonPath('data.item.canUseInFutureCase', true);
        $this->getJson('/api/user/contexts/'.$id.'/versions?sortDirection=asc')->assertOk()
            ->assertJsonPath('data.pagination.total', 4)->assertJsonPath('data.items.0.payload.facts.0.value', 'Review my store API');
    }

    public function test_archive_restore_delete_and_idempotent_delete(): void
    {
        $id = $this->makeContext();
        $this->postJson('/api/user/contexts/'.$id.'/archive', ['expectedVersion' => 1], $this->key())->assertOk()->assertJsonPath('data.item.canUseInFutureCase', false);
        $this->patchJson('/api/user/contexts/'.$id, ['title' => 'Not editable', 'expectedVersion' => 2])->assertConflict();
        $this->postJson('/api/user/contexts/'.$id.'/restore', ['expectedVersion' => 2], $this->key())->assertOk();
        $key = $this->key();
        $this->deleteJson('/api/user/contexts/'.$id, ['expectedVersion' => 3], $key)->assertOk()->assertJsonPath('data.item.title', null)->assertJsonMissingPath('data.item.facts');
        $this->deleteJson('/api/user/contexts/'.$id, ['expectedVersion' => 3], $key)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->getJson('/api/user/contexts/'.$id)->assertNotFound();
        $this->getJson('/api/user/contexts')->assertJsonPath('data.pagination.total', 0);
        $this->assertDatabaseCount('specialized_context_versions', 4);
    }

    public function test_context_and_history_are_not_visible_to_another_user(): void
    {
        $id = $this->makeContext();
        $this->asAccount(User::factory()->create());
        $this->getJson('/api/user/contexts/'.$id)->assertNotFound();
        $this->getJson('/api/user/contexts/'.$id.'/versions')->assertNotFound();
        $this->patchJson('/api/user/contexts/'.$id, ['title' => 'Unauthorized', 'expectedVersion' => 1])->assertNotFound();
        $this->postJson('/api/user/contexts/'.$id.'/archive', ['expectedVersion' => 1], $this->key())->assertNotFound();
        $this->deleteJson('/api/user/contexts/'.$id, ['expectedVersion' => 1], $this->key())->assertNotFound();
    }

    public function test_context_idempotency_and_validation(): void
    {
        $key = $this->key();
        $body = $this->contextPayload();
        $this->postJson('/api/user/contexts', $body, $key)->assertCreated();
        $this->postJson('/api/user/contexts', $body, $key)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $body['title'] = 'Different';
        $this->postJson('/api/user/contexts', $body, $key)->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('specialized_contexts', 1);
        $body['domain'] = 'medical';
        $this->postJson('/api/user/contexts', $body, $this->key())->assertUnprocessable();
        $body = $this->contextPayload();
        $body['facts'][0]['secret'] = 'unexpected';
        $this->postJson('/api/user/contexts', $body, $this->key())->assertUnprocessable();
        $this->getJson('/api/user/contexts?sortBy=password')->assertUnprocessable();
    }

    public function test_stale_edit_never_overwrites_a_new_version_or_changes_domain(): void
    {
        $id = $this->makeContext();
        $this->patchJson('/api/user/contexts/'.$id, ['title' => 'New title', 'expectedVersion' => 1])->assertOk();
        $this->patchJson('/api/user/contexts/'.$id, ['title' => 'Stale title', 'expectedVersion' => 1])->assertConflict();
        $this->patchJson('/api/user/contexts/'.$id, ['domain' => 'business', 'expectedVersion' => 2])->assertUnprocessable();
        $this->assertSame(2, SpecializedContext::find($id)->current_version);
    }
}
