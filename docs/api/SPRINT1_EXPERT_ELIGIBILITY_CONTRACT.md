# Sprint 1 API contract — evidence-backed Expert KYC approval

Status: **implemented in source; PHP/migration runtime verification pending**. The existing `/api/*` URLs and response envelopes are retained. This is a **behaviorally breaking request change** for the Admin approval screen: the previous empty approval request now returns HTTP 422.

## What happens now

The Expert continues to register, log in, save KYC drafts, upload private documents, and access the limited KYC workspace before approval. At submission, existing identity and CV/experience checks still apply. The Admin must review every submitted document, then select at least one scope with evidence from this specific application. A rejected approval request leaves application state and existing scopes unchanged because verification runs before the transactional state transition.

`config/expert_verification.php` classifies initial domains. Unknown domains fail closed with `422 scopes.N.domain` until a reviewed domain policy is added. Classification is an application approval policy, not a legal claim that every service in every country has identical licensing rules. An Admin must consult the appropriate current authority for the requested country and service. The backend records that review; it does not contact regulators automatically.

## Existing endpoint: `POST /api/admin/kyc/applications/{application}/approve`

Authorization remains `auth:sanctum`, Admin `admin:access`, and the existing `experts.reviewKyc` permission via `Gate::authorize(...)`. Successful HTTP status, response envelope, route name, and account model stay unchanged.

### Regulated example (`legal` in `JO`)

```json
{
  "scopes": [{
    "domain": "legal",
    "jurisdiction": "Jordan",
    "jurisdictionCountry": "JO",
    "role": "Legal consultant",
    "serviceTypes": ["written_consultation"],
    "languages": ["ar"],
    "evidence": {"type": "credential", "id": 123},
    "professionalReview": {
      "verifiedCountry": "JO",
      "regulator": "Relevant professional authority",
      "registrationNumber": "EXAMPLE-123",
      "verificationSource": "https://example.invalid/official-register",
      "statusChecked": "active",
      "nextReviewAt": "2027-03-28"
    }
  }]
}
```

The `credential` must be an unexpired `license` owned by this KYC application, with a linked and reviewed private document. `jurisdictionCountry` identifies the licensed country (two-letter country code), and `verifiedCountry` must match it. The Expert may reside in another country. The scope domain and jurisdiction must match the reviewed application; when the requested practice jurisdiction differs from the application, the Expert must update and resubmit the KYC application first. The reviewer records an active status and an HTTPS source. `nextReviewAt` must be after today and within one year. The scope's `validUntil` is bounded by both the licence expiry and next review; when omitted it is set to the earlier date. A supplied `validUntil` may be earlier, never later. The licence screenshot alone does not automatically establish registration status; the Admin remains responsible for actually checking the authoritative register.

For example, an Expert residing in `JO` can submit an application with `jurisdiction: "England and Wales"`, then receive a legal scope with `jurisdictionCountry: "GB"` after the Admin verifies a current licence there. One verified country is sufficient for the selected scope; the system does not infer approval in other countries.

### Nonregulated example (`technology`)

```json
{
  "scopes": [{
    "domain": "technology",
    "jurisdiction": "Jordan",
    "jurisdictionCountry": "JO",
    "role": "Software consultant",
    "serviceTypes": ["written_consultation"],
    "languages": ["ar"],
    "evidence": {"type": "experience", "id": 456}
  }]
}
```

Evidence may refer to a credential, qualification, or experience from the same application. A credential or qualification needs its linked reviewed document; experience needs a reviewed CV or work sample. A professional licence is not fabricated for a nonregulated domain. If the selected credential expires, the scope cannot outlast it. Each new scope stores private evidence and reviewer metadata; no private path, registration number, or verification source is included in Expert/public `verifiedScopes` responses.

## Response and errors

On approval, the existing `data.application.verifiedScopes` envelope is retained and gains the public-safe `jurisdictionCountry` field (nullable for legacy scopes). **Admin only:** `GET /api/admin/kyc/applications/{application}` and the Admin approval response include an additive `data.application.professionalReviews` array with `scopeId`, `evidenceType`, `evidenceId`, `evidenceDocumentId`, `verifiedCountry`, `regulator`, `registrationNumber`, `verificationSource`, `statusChecked`, `checkedAt`, and `nextReviewAt`. Values for legacy scopes may be null. Do not show this private review block in the Expert or public UI.

`422` uses the existing Laravel validation response (`message`, `errors` with dot notation). Important keys: `scopes`, `scopes.N.domain`, `scopes.N.jurisdictionCountry`, `scopes.N.evidence`, `scopes.N.evidence.id`, `scopes.N.professionalReview.verifiedCountry`, `scopes.N.professionalReview.nextReviewAt`, and `scopes.N.validUntil`. Missing authorization remains `401` or `403`; conflicting KYC state remains `409`. No new response code or URL version was introduced. Do not interpret `422` as approved and do not retry an empty approval request.

## Frontend rollout order

1. Before deploying backend approval validation, update the Admin KYC detail view to let a reviewer select evidence IDs from the application's `credentials`/`qualifications`/`experiences` and see linked reviewed documents.
2. For regulated domains, add licensed `jurisdictionCountry`, matching `verifiedCountry`, regulator, registration number, HTTPS verification source, active status and next review date inputs. Show field-level `422` errors. Do not claim the platform checked a registry automatically.
3. Expert KYC already accepts credentials and linked private document uploads. Ensure its form exposes licence fields and the credential document upload for regulated domains, and handles a request for more information. Expert login and KYC workspace are unchanged.
4. Deploy the backend migration and code in Staging, run the KYC tests, and smoke-check Admin approval end to end before Production. Coordinate the frontend/backend release so the Admin screen never sends an empty approval payload to the new backend.

## Legacy and later work

Existing approved scopes are preserved with null review metadata; the migration does not invent evidence or revoke them silently. Inventory and re-review them before regulated booking is enabled. There is no Case/booking purchase gate, regulator integration, scope extension, manual renewal endpoint, or automatic source recheck in this patch. Regulated scopes stop being effective after their bounded `validUntil`, using the existing effectiveness checks. Add renewal/suspension and cross-jurisdiction extension with their own contract and tests before offering that lifecycle publicly.
