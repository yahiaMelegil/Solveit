<?php

namespace Tests\Feature\Catalog;

use App\Models\Expert;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class CatalogContractTest extends CatalogTestCase
{
    protected function freezeFixtureClock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01T12:00:00Z'));
    }

    private function capture(string $name, string $method, string $url, array $body = [], int $status = 200): array
    {
        $headers = $method === 'GET' ? [] : $this->key();
        $response = $this->json($method, $url, $body, $headers)->assertStatus($status);
        $data = $response->json();
        if ($dir = getenv('SOLVEIT_CATALOG_FIXTURE_DIR')) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }file_put_contents($dir.'/'.$name.'.json', json_encode(['contractVersion' => '2.0.0', 'synthetic' => true, 'request' => ['method' => $method, 'url' => $url, 'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer <ACCOUNT_TOKEN>'] + $headers, 'body' => (object) $body], 'response' => ['status' => $status, 'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID'), 'Retry-After' => $response->headers->get('Retry-After')], 'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR)]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        }

        return $data['data']['item'] ?? $data;
    }

    public function test_v2_contract_fixtures_are_actual_responses(): void
    {
        $this->capture('catalog-domains', 'GET', '/api/catalog/domains');
        $this->capture('catalog-specialties', 'GET', '/api/catalog/specialties');
        $this->capture('catalog-countries', 'GET', '/api/catalog/countries');
        $this->asAccount($this->catalogAdmin);
        $v = $this->capture('admin-policy-created', 'POST', '/api/admin/catalog/entries', ['code' => 'fixture_global_policy', 'policy' => $this->policyData()], 201);
        $policyUrl = '/api/admin/catalog/versions/'.$v['id'];
        $v = $this->capture('admin-policy-reviewed', 'POST', $policyUrl.'/review', ['expectedVersion' => $v['revision'], 'approved' => true, 'reasonCode' => 'POLICY_REVIEW']);
        $impact = $this->capture('admin-publish-impact', 'GET', $policyUrl.'/impact');
        $v = $this->capture('admin-policy-published', 'POST', $policyUrl.'/publish', ['expectedVersion' => $v['revision'], 'status' => 'enabled', 'impactToken' => $impact['impactToken']]);
        $this->asAccount($this->owner);
        $this->capture('catalog-entry-schema', 'GET', '/api/catalog/intake-schema/'.$v['catalogEntryId']);
        $c = $this->capture('case-draft', 'POST', '/api/v2/user/cases', ['title' => 'مراجعة نظام متجر', 'problemDescription' => 'أحتاج مراجعة برمجية لمتجري', 'desiredOutcome' => 'تحسين موثوقية النظام', 'caseCountry' => 'PS', 'language' => 'ar', 'urgency' => 'normal', 'answers' => ['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => false]], 201);
        $url = '/api/v2/user/cases/'.$c['id'];
        $c = $this->capture('case-scopes', 'PUT', $url.'/scopes', ['expectedVersion' => $c['version'], 'scopes' => [$this->scopeInput($v)]]);
        $this->capture('waiting-for-expert-supply', 'GET', $url.'/readiness');
        $this->expertGrant($v);
        $c = $this->capture('case-assessment', 'POST', $url.'/assessment', ['expectedVersion' => $c['version']]);
        $this->capture('case-readiness', 'GET', $url.'/readiness');
        $c = $this->capture('case-confirmed', 'POST', $url.'/confirm', ['expectedVersion' => $c['version'], 'confirmed' => true]);
        $c = $this->capture('case-ready', 'POST', $url.'/submit', ['expectedVersion' => $c['version']]);
        $this->capture('case-detail', 'GET', $url);
        $this->capture('case-list', 'GET', '/api/v2/user/cases?perPage=10&status=ready_for_matching');
        $this->capture('legacy-upgrade-409', 'POST', '/api/user/cases/'.$c['id'].'/submit', ['expectedVersion' => $c['version']], 409);
        $this->asAccount($this->catalogAdmin);
        $this->capture('admin-case-metadata', 'GET', '/api/v2/admin/cases/'.$c['id']);
        $this->capture('catalog-impact', 'GET', '/api/admin/catalog/versions/'.$v['id'].'/impact');
        $this->asAccount($this->owner);
        $this->capture('self-only-422', 'POST', '/api/v2/user/cases', ['subjectType' => 'other'], 422);
        $this->capture('stale-cancel-409', 'POST', $url.'/cancel', ['expectedVersion' => 1], 409);
        $this->capture('case-cancelled', 'POST', $url.'/cancel', ['expectedVersion' => $c['version']]);
        foreach (['immediateDanger' => 'safety-referral', 'requiresInPerson' => 'remote-not-allowed', 'ambiguousHighRisk' => 'needs-clarification'] as $risk => $fixture) {
            $intake = ['answers' => array_replace(['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => false], [$risk => true])];
            $blocked = $this->select($this->draft($intake), [$this->scopeInput($v)]);
            $this->capture($fixture, 'GET', '/api/v2/user/cases/'.$blocked['id'].'/readiness');
        }
        $other = $this->entry();
        $partial = $this->select($this->draft(), [$this->scopeInput($v), $this->scopeInput($other)]);
        $partial = $this->action($partial, 'confirm', ['confirmed' => true]);
        $partialUrl = '/api/v2/user/cases/'.$partial['id'];
        $subset = ['expectedVersion' => $partial['version'], 'selectedScopeIds' => [$partial['scopes'][0]['id']]];
        $this->capture('partial-consent-required-409', 'POST', $partialUrl.'/submit', $subset, 409);
        $this->capture('partial-submitted-parent-waiting', 'POST', $partialUrl.'/submit', $subset + ['partialConsent' => true]);
        $this->asAccount(User::factory()->create());
        $this->capture('foreign-case-404', 'GET', $url, [], 404);
        $this->asAccount(Expert::factory()->create(), ['*']);
        $this->capture('expert-forbidden-403', 'GET', $url, [], 403);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer invalid');
        $this->capture('guest-401', 'GET', $url, [], 401);
        $this->asAccount($this->owner);
        RateLimiter::for('cases-read', fn () => Limit::perMinute(1)->by('catalog-contract'));
        $this->getJson('/api/v2/user/cases')->assertOk();
        $this->capture('rate-limit-429', 'GET', '/api/v2/user/cases', [], 429);
    }
}
