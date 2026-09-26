<?php

namespace Tests\Feature\Expert\Auth;

use App\Models\Admin;
use App\Models\Expert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_authenticated_expert_state(): void
    {
        $expert = Expert::factory()->create();

        $this->withToken($this->expertToken($expert))
            ->getJson('/api/expert/auth/me')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Expert retrieved successfully.')
            ->assertJsonPath('data.expert.id', $expert->id)
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('data.kyc_status', 'not_submitted')
            ->assertJsonMissingPath('data.expert.password')
            ->assertJsonMissingPath('data.expert.remember_token');
    }

    public function test_missing_or_invalid_tokens_are_rejected(): void
    {
        $this->getJson('/api/expert/auth/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => false,
                'message' => 'Unauthenticated.',
            ]);

        $this->withToken(Str::random(80))
            ->getJson('/api/expert/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_regular_user_and_administrator_tokens_cannot_access_expert_routes(): void
    {
        $userToken = User::factory()->create()->createToken('Web')->plainTextToken;
        $adminToken = Admin::factory()->create()
            ->createToken('Admin', [Admin::ACCESS_ABILITY])
            ->plainTextToken;

        foreach ([$userToken, $adminToken] as $token) {
            $this->app['auth']->forgetGuards();

            $this->withToken($token)
                ->getJson('/api/expert/auth/me')
                ->assertForbidden()
                ->assertExactJson([
                    'status' => false,
                    'message' => 'You are not authorized to access the expert area.',
                ]);
        }
    }

    public function test_an_expert_token_without_the_required_ability_is_rejected(): void
    {
        $expert = Expert::factory()->create();
        $token = $expert->createToken('Limited', [])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expert/auth/me')
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'message' => 'You are not authorized to access the expert area.',
            ]);
    }

    public function test_an_inactive_expert_cannot_use_an_existing_token(): void
    {
        $expert = Expert::factory()->create();
        $token = $this->expertToken($expert);
        $expert->update(['is_active' => false]);

        $this->withToken($token)
            ->getJson('/api/expert/auth/me')
            ->assertForbidden()
            ->assertExactJson([
                'status' => false,
                'message' => 'This expert account is currently unavailable.',
            ]);
    }

    public function test_change_password_validates_the_current_password(): void
    {
        $expert = Expert::factory()->create([
            'password' => Hash::make('current-password'),
        ]);
        $token = $this->expertToken($expert);

        $this->withToken($token)
            ->putJson('/api/expert/auth/password', [
                'current_password' => 'incorrect-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertUnprocessable()
            ->assertExactJson([
                'status' => false,
                'message' => 'The provided data is invalid.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ]);

        $this->assertTrue(Hash::check('current-password', $expert->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_change_password_preserves_the_current_token_and_revokes_other_expert_tokens(): void
    {
        $expert = Expert::factory()->create([
            'password' => Hash::make('current-password'),
        ]);
        $otherExpert = Expert::factory()->create();
        $currentToken = $this->expertToken($expert, 'Current');
        $otherToken = $this->expertToken($expert, 'Other');
        $unrelatedToken = $this->expertToken($otherExpert, 'Unrelated');
        $currentTokenId = PersonalAccessToken::findToken($currentToken)->getKey();
        $otherTokenId = PersonalAccessToken::findToken($otherToken)->getKey();
        $unrelatedTokenId = PersonalAccessToken::findToken($unrelatedToken)->getKey();

        $this->withToken($currentToken)
            ->putJson('/api/expert/auth/password', [
                'current_password' => 'current-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Expert password changed successfully.',
                'data' => null,
            ]);

        $this->assertTrue(Hash::check('new-password123', $expert->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentTokenId]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherTokenId]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $unrelatedTokenId]);

        $this->app['auth']->forgetGuards();
        $this->withToken($currentToken)->getJson('/api/expert/auth/me')->assertOk();
    }

    public function test_logout_revokes_only_the_current_token_and_it_cannot_be_reused(): void
    {
        $expert = Expert::factory()->create();
        $currentToken = $this->expertToken($expert, 'Current');
        $otherToken = $this->expertToken($expert, 'Other');
        $currentTokenId = PersonalAccessToken::findToken($currentToken)->getKey();
        $otherTokenId = PersonalAccessToken::findToken($otherToken)->getKey();

        $this->withToken($currentToken)
            ->postJson('/api/expert/auth/logout')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Expert logged out successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentTokenId]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherTokenId]);

        $this->app['auth']->forgetGuards();
        $this->withToken($currentToken)
            ->getJson('/api/expert/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_all_revokes_only_the_authenticated_experts_tokens(): void
    {
        $expert = Expert::factory()->create();
        $otherExpert = Expert::factory()->create();
        $currentToken = $this->expertToken($expert, 'Current');
        $this->expertToken($expert, 'Other');
        $otherExpertsToken = $this->expertToken($otherExpert, 'Unrelated');
        $otherExpertsTokenId = PersonalAccessToken::findToken($otherExpertsToken)->getKey();

        $this->withToken($currentToken)
            ->postJson('/api/expert/auth/logout-all')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'message' => 'Expert logged out from all devices successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $expert->id,
            'tokenable_type' => Expert::class,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherExpertsTokenId]);
    }

    private function expertToken(Expert $expert, string $name = 'Expert Dashboard'): string
    {
        return $expert->createToken($name, [Expert::ACCESS_ABILITY])->plainTextToken;
    }
}
