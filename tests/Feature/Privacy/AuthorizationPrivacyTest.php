<?php

namespace Tests\Feature\Privacy;

use App\Models\Admin;
use App\Models\Expert;
use App\Models\User;
use Database\Seeders\PrivacyPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AuthorizationPrivacyTest extends PrivacyTestCase
{
    public function test_additive_upgrade_seeder_preserves_existing_role_customizations(): void
    {
        $role = Role::findByName('user_manager', 'admin');
        $role->givePermissionTo('support.update');
        $super = Role::findByName('super_admin', 'admin');
        $super->revokePermissionTo('dataRequests.viewAny');
        $this->seed(PrivacyPermissionsSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('support.update'));
        $this->assertFalse($role->fresh()->hasPermissionTo('dataRequests.viewAny'));
        $this->assertTrue($super->fresh()->hasPermissionTo('dataRequests.viewAny'));
    }

    public function test_guest_wrong_account_and_missing_ability_are_blocked(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer invalid');
        $this->getJson('/api/user/profile')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        foreach ([Admin::factory()->create(), Expert::factory()->verified()->create()] as $actor) {
            $this->asAccount($actor, ['user:access', 'admin:access', 'expert:access']);
            foreach (['profile', 'preferences', 'contexts', 'consents', 'data-requests'] as $path) {
                $this->getJson('/api/user/'.$path)->assertForbidden();
            }
        }
        $this->asAccount($this->owner, ['unrelated']);
        $this->getJson('/api/user/profile')->assertForbidden();
        $this->asAccount(User::factory()->unverified()->create());
        $this->getJson('/api/user/profile')->assertForbidden()->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_super_admin_cannot_enter_user_private_workspace(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->asAccount($admin, ['user:access', 'admin:access']);
        $this->getJson('/api/user/contexts')->assertForbidden();
        $this->postJson('/api/user/contexts', $this->contextPayload(), $this->key())->assertForbidden();
    }

    public function test_admin_permission_is_checked_before_validation_and_object_lookup(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('user_manager');
        $this->asAccount($admin);
        $this->getJson('/api/admin/data-requests?sortBy=invalid')->assertForbidden();
        $this->getJson('/api/admin/data-requests/999999')->assertForbidden();
        $this->getJson('/api/admin/users/'.$this->owner->id.'/consents?perPage=bad')->assertForbidden();
    }

    public function test_explicit_admin_permissions_expose_metadata_only_and_are_audited(): void
    {
        $this->consent();
        $id = $this->requestData();
        $this->process($id);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['users.consentMetadata.view', 'dataRequests.viewAny', 'dataRequests.view']);
        $this->asAccount($admin);
        $this->getJson('/api/admin/users/'.$this->owner->id.'/consents')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonMissingPath('data.items.0.content');
        $this->getJson('/api/admin/data-requests/'.$id)->assertOk()->assertJsonPath('data.item.status', 'completed')
            ->assertJsonMissingPath('data.item.artifact_path')->assertJsonMissingPath('data.item.downloadAvailable')->assertJsonMissingPath('data.item.outcome');
        $this->getJson('/api/admin/data-requests?type=export&userId='.$this->owner->id)->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->assertDatabaseHas('audit_events', ['actor_type' => 'admin', 'actor_id' => $admin->id, 'action' => 'admin.data_request_metadata_viewed']);
        $this->getJson('/api/user/data-requests/'.$id.'/download')->assertForbidden();
    }

    public function test_revoked_permission_blocks_the_same_token_on_next_request(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('dataRequests.viewAny');
        $token = $this->asAccount($admin);
        $this->getJson('/api/admin/data-requests')->assertOk();
        $admin->revokePermissionTo('dataRequests.viewAny');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/data-requests')->assertForbidden();
    }

    public function test_confirmation_rate_limit_and_cors_headers_are_available(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/user/security/confirm-password', ['currentPassword' => 'wrong', 'purpose' => 'export'])->assertUnprocessable();
        }
        $this->postJson('/api/user/security/confirm-password', ['currentPassword' => 'wrong', 'purpose' => 'export'])->assertStatus(429)->assertHeader('Retry-After');
        config()->set('cors.allowed_origins', ['http://localhost:3000']);
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization, Idempotency-Key'])
            ->options('/api/user/contexts')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->assertContains('Idempotency-Key', config('cors.allowed_headers'));
        $this->assertContains('Retry-After', config('cors.exposed_headers'));
    }

    public function test_user_and_expert_tokens_with_same_numeric_id_remain_isolated(): void
    {
        $expert = Expert::factory()->verified()->create(['id' => $this->owner->id]);
        $id = $this->makeContext();
        $this->asAccount($expert, ['user:access']);
        $this->getJson('/api/user/contexts/'.$id)->assertForbidden();
    }

    public function test_context_list_does_not_issue_one_version_query_per_record(): void
    {
        $this->makeContext();
        DB::enableQueryLog();
        $this->getJson('/api/user/contexts')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->makeContext();
        $this->makeContext();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/user/contexts')->assertOk();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 1, $many);
    }
}
