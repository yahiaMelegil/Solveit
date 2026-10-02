<?php

namespace Tests\Feature\Catalog;

use App\Models\Admin;
use App\Models\CaseRecord;
use App\Models\CatalogVersion;
use App\Models\Expert;
use App\Models\ExpertKycApplication;
use App\Models\ExpertVerifiedScope;
use App\Services\Catalog\CaseReadiness;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Privacy\PrivacyTestCase;

abstract class CatalogTestCase extends PrivacyTestCase
{
    protected Admin $catalogAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ServiceCatalogSeeder::class);
        $this->consent('terms');
        $this->consent('privacy');
        $this->catalogAdmin = Admin::factory()->create();
        $this->catalogAdmin->assignRole('super_admin');
    }

    protected function policyData(array $changes = []): array
    {
        $p = CatalogVersion::firstOrFail()->policy;

        return array_replace($p, ['effectiveFrom' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'commerciallyAvailable' => true, 'operationallyAvailable' => true, 'matchingRules' => ['keywords' => ['software', 'برمجة'], 'prohibitedKeywords' => ['malware attack'], 'humanReviewRequired' => false]], $changes);
    }

    protected function entry(array $changes = [], bool $publish = true): array
    {
        $this->asAccount($this->catalogAdmin);
        $v = $this->postJson('/api/admin/catalog/entries', ['code' => 'test_'.bin2hex(random_bytes(6)), 'policy' => $this->policyData($changes)], $this->key())->assertCreated()->json('data.item');
        $v = $this->postJson('/api/admin/catalog/versions/'.$v['id'].'/review', ['expectedVersion' => $v['revision'], 'approved' => true, 'reasonCode' => 'POLICY_REVIEWED'], $this->key())->assertOk()->json('data.item');
        if ($publish) {
            $impact = $this->getJson('/api/admin/catalog/versions/'.$v['id'].'/impact')->assertOk()->json('data.item');
            $v = $this->postJson('/api/admin/catalog/versions/'.$v['id'].'/publish', ['expectedVersion' => $v['revision'], 'status' => 'enabled', 'impactToken' => $impact['impactToken']], $this->key())->assertOk()->json('data.item');
        }
        $this->asAccount($this->owner);

        return $v;
    }

    protected function expertGrant(array $v, string $jurisdiction = 'GLOBAL', string $country = 'JO'): ExpertVerifiedScope
    {
        $e = Expert::factory()->verified()->create(['country' => $country, 'domain' => $v['policy']['domain']]);
        $e->forceFill(['kyc_status' => 'approved'])->save();
        $app = ExpertKycApplication::factory()->create(['expert_id' => $e->id, 'status' => 'verified', 'domain' => $v['policy']['domain'], 'country' => $country, 'jurisdiction' => $country]);
        $q = DB::table('expert_kyc_qualifications')->insertGetId(['application_id' => $app->id, 'degree' => 'Computer Science', 'institution' => 'Synthetic University', 'created_at' => now(), 'updated_at' => now()]);
        $doc = DB::table('expert_kyc_documents')->insertGetId(['application_id' => $app->id, 'document_type' => 'qualification', 'qualification_id' => $q, 'disk' => 'kyc', 'path' => 'synthetic/private.pdf', 'original_name' => 'qualification.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'checksum' => hash('sha256', 'synthetic'), 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $s = $e->verifiedScopes()->create(['kyc_application_id' => $app->id, 'domain' => $v['policy']['domain'], 'jurisdiction' => $country, 'verified_country' => $country, 'role' => 'Consultant', 'languages' => ['ar', 'en'], 'service_types' => ['written_consultation', 'video_consultation', 'document_review'], 'status' => 'active', 'valid_from' => today()->subYear(), 'valid_until' => today()->addYear(), 'next_review_at' => today()->addMonths(6), 'evidence_type' => 'qualification', 'evidence_id' => $q, 'evidence_document_id' => $doc]);
        $e->profile()->create(['slug' => 'synthetic-'.$e->id, 'professional_title' => 'Architect', 'bio' => str_repeat('Synthetic experience. ', 5), 'public_languages' => ['ar', 'en'], 'is_published' => true]);
        $e->profile->forceFill(['is_published' => true])->save();
        $e->availability()->create(['timezone' => 'UTC', 'service_modes' => ['written_consultation', 'video_consultation', 'document_review'], 'weekly_schedule' => [], 'blackout_dates' => [], 'max_active_requests' => 3, 'accepting_new_requests' => true]);
        $this->asAccount($this->catalogAdmin);
        $this->postJson('/api/admin/catalog/grants', ['scopeId' => $s->id, 'catalogVersionId' => $v['id'], 'jurisdictionCode' => $jurisdiction, 'evidenceId' => $q, 'reasonCode' => 'EVIDENCE_REVIEWED'], $this->key())->assertCreated();
        $this->asAccount($this->owner);

        return $s;
    }

    protected function draft(array $changes = []): array
    {
        $this->asAccount($this->owner);

        return $this->postJson('/api/v2/user/cases', array_replace(['title' => 'Software review', 'problemDescription' => 'Review my software architecture', 'desiredOutcome' => 'A reliable service', 'caseCountry' => 'PS', 'language' => 'en', 'urgency' => 'normal', 'subjectType' => 'self', 'answers' => ['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => false]], $changes), $this->key())->assertCreated()->json('data.item');
    }

    protected function scopeInput(array $v, array $changes = []): array
    {
        return array_replace(['catalogEntryId' => $v['catalogEntryId'], 'catalogVersion' => $v['catalogVersion'], 'deliveryMode' => 'written_consultation', 'jurisdictionCodes' => $v['policy']['jurisdictionCodes'], 'answers' => [], 'confirmed' => true], $changes);
    }

    protected function select(array $c, array $scopes): array
    {
        return $this->putJson('/api/v2/user/cases/'.$c['id'].'/scopes', ['expectedVersion' => $c['version'], 'scopes' => $scopes], $this->key())->assertOk()->json('data.item');
    }

    protected function action(array $c, string $action, array $extra = []): array
    {
        $response = $this->postJson('/api/v2/user/cases/'.$c['id'].'/'.$action, ['expectedVersion' => $c['version']] + $extra, $this->key());
        $this->assertSame(200, $response->status(), $response->getContent().' '.json_encode(app(CaseReadiness::class)->assess(CaseRecord::find($c['id']))));

        return $response->json('data.item');
    }

    protected function readyCase(array $v): array
    {
        $c = $this->select($this->draft(), [$this->scopeInput($v)]);
        $c = $this->action($c, 'confirm', ['confirmed' => true]);

        return $this->action($c, 'submit');
    }
}
