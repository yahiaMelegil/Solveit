<?php

namespace Tests\Support;

use App\Models\Admin;
use App\Models\CatalogVersion;
use App\Models\Expert;
use App\Models\ExpertCatalogGrant;
use App\Models\ExpertKycApplication;
use Illuminate\Support\Facades\DB;

final class CatalogSupplyFixture
{
    public static function make(CatalogVersion $v): void
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Synthetic test supply only.');
        }
        $admin = Admin::factory()->create();
        $e = Expert::factory()->verified()->create(['country' => 'JO', 'domain' => $v->policy['domain']]);
        $e->forceFill(['kyc_status' => 'approved'])->save();
        $app = ExpertKycApplication::factory()->create(['expert_id' => $e->id, 'status' => 'verified', 'domain' => $v->policy['domain'], 'country' => 'JO', 'jurisdiction' => 'Jordan']);
        $id = DB::table('expert_kyc_qualifications')->insertGetId(['application_id' => $app->id, 'degree' => 'Software engineering', 'institution' => 'Synthetic University', 'created_at' => now(), 'updated_at' => now()]);
        $document = DB::table('expert_kyc_documents')->insertGetId(['application_id' => $app->id, 'document_type' => 'qualification', 'qualification_id' => $id, 'disk' => 'kyc', 'path' => 'synthetic/fixture.pdf', 'original_name' => 'fixture.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'checksum' => hash('sha256', 'synthetic'), 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $s = $e->verifiedScopes()->create(['kyc_application_id' => $app->id, 'domain' => $v->policy['domain'], 'jurisdiction' => 'Jordan', 'verified_country' => 'JO', 'role' => 'Consultant', 'languages' => ['ar', 'en'], 'service_types' => $v->policy['deliveryModes'], 'status' => 'active', 'valid_from' => today()->subYear(), 'valid_until' => today()->addYear(), 'next_review_at' => today()->addMonths(6), 'evidence_type' => 'qualification', 'evidence_id' => $id, 'evidence_document_id' => $document]);
        $profile = $e->profile()->create(['slug' => 'synthetic-'.$e->id, 'professional_title' => 'Architect', 'bio' => str_repeat('Synthetic. ', 20), 'public_languages' => ['ar', 'en']]);
        $profile->forceFill(['is_published' => true])->save();
        $e->availability()->create(['timezone' => 'UTC', 'service_modes' => $v->policy['deliveryModes'], 'weekly_schedule' => [], 'blackout_dates' => [], 'max_active_requests' => 3, 'accepting_new_requests' => true]);
        $g = new ExpertCatalogGrant;
        $g->forceFill(['scope_id' => $s->id, 'catalog_version_id' => $v->id, 'jurisdiction_code' => 'GLOBAL', 'reviewed_by' => $admin->id, 'evidence_type' => 'qualification', 'evidence_id' => $id])->save();
    }
}
