# ADR-002: Expert eligibility and scoped professional verification

Status: Accepted as a product rule on 2026-09-28. Not part of Sprint 0. Sprint 1 approval enforcement is implemented in source, with runtime verification pending.

## Decision

- Any Expert may register and use the limited verification workspace before KYC approval. Do not block login on pending KYC.
- Final Expert KYC approval requires verified identity and at least one approved, effective service scope. A public profile requires an active Expert, approved KYC, and at least one effective scope.
- A scope specifies domain, service, role, and relevant jurisdiction. For regulated services, the reviewer must verify current professional authority with the relevant regulator or an appropriate authoritative source, including the permitted jurisdiction (country, state, or other applicable subdivision), status, and expiry or recheck date. A certificate image by itself is insufficient evidence of current authority.
- For nonregulated services, review appropriate experience, work samples, qualifications, or other proportionate evidence. Do not invent a mandatory professional licence.
- Keep identity-review outcome, evidence-review outcome, and individual scope status distinguishable internally even if the existing public API retains its `kyc_status` field. A rejected or expired scope does not invalidate an independently verified identity or other active scopes.
- Expiry, suspension, or material new information must stop that particular scope from being offered and trigger re-verification and review of affected active Cases, in line with SRS BR-017.
- Global signup and discovery do not grant universal authority to practise. Future Case acceptance and paid booking must check the requested service, expert scope, relevant client/service jurisdiction, catalogue launch gate, and payment/payout coverage.
- Regulated health, licensed legal/tax/investment, emergencies and other location-specific services remain behind separate product/policy approval gates under SRS 3.3 and BR-020. A verified expert can exist before that service is purchasable.
- Do not expose private identity or professional evidence on the public profile (SRS BR-016).

## Evidence in the backend before Sprint 1

- KYC credentials and private documents exist; `ensureComplete()` currently requires identity and a CV **or** experience, but does not require a licence for the `legal` domain.
- `ApproveKycApplicationRequest` currently accepts omitted `scopes`; `ExpertKycWorkflow::replaceVerifiedScopes()` then creates an active default scope with no credential check or expiry. `AdminKycTest::submittedApplication()` uses a legal-domain applicant with empty `credentials` and still approves it.
- `ExpertProfileManager::missingRequirements()` and the public profile endpoint require an effective scope. The scope has domain, jurisdiction, role, service types, validity dates, and status; it does not record regulator, registration number, verification source/result, or recheck details.
- An approval request currently forces every scope's domain and jurisdiction to equal the single KYC application domain/jurisdiction. A later scope extension or separate jurisdiction cannot be approved with the existing API.
- There is no Case, matching, or paid booking eligibility check yet. Visibility checks do not by themselves enforce cross-border service permission.

## Implementation boundary after Sprint 0

1. Agree a small per-service policy catalogue: evidence type, regulated flag, permissible jurisdictions, renewal rule, reviewer authority, and launch state. Keep unsupported regulated services blocked for purchase.
2. Add explicit regulator/registration verification evidence and per-scope review results with an additive schema change; preserve existing records and private files. Define how legacy approved scopes are reviewed or limited before any new purchase flow.
3. Enforce the relevant evidence and at least one approved scope at approval; remove the implicit active-scope fallback for regulated services. Maintain a compatible API path and response until the React owners have migrated any affected request payload.
4. Add scope extension, suspension, expiry/recheck and audit actions with Admin permission checks. Keep the Expert KYC workspace available when no scope is active.
5. Test licensed/no-licence approvals, nonregulated evidence, country/state matching, expired/revoked scopes, the public profile, and the future Case/booking gate. Document the exact frontend contract before changing a used response.

Sprint 0 delivered no application code, migration, endpoint, or frontend feature for this decision.

## Sprint 1 implementation boundary

The existing approval endpoint now requires an explicit scope and reviewed evidence from the same KYC application. Regulated domains require a linked active licence review with a licensed scope country, registration, regulator, authoritative HTTPS source, and bounded next-review date. The scope country can differ from Expert residence; the application jurisdiction text must match the scope. Approval in one country never implies authority in another. Unknown domains fail closed until their policy is classified. Approval remains a manual Admin attestation, not an automated regulator lookup. The new nullable scope columns preserve historic records without inventing verification evidence. The public-safe `jurisdictionCountry` field is additive to verified scope responses; private review metadata remains Admin only. Existing approvals need a separate re-review before regulated booking. Scope renewal, adding another jurisdiction through a verified amendment, policy-specific service catalogue and booking gates remain future work. See `docs/api/SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md` for the request/response and frontend impact. PHP tests, migration and Staging checks are still pending.

## 2026-09-30 — accepted single-scope renewal addition

Historical future-work/runtime-pending notes above describe their original delivery. Single-scope
renewal is now implemented under docs/api/SPRINT1_EXPERT_RENEWAL_CONTRACT.md and locally tested.
The evidence decision in this ADR is unchanged. Approval appends evidence and creates one successor
scope, preserving prior evidence/identity/other scopes. New-country extensions, active Case handling,
formal appeal and automated regulator checks remain future work; no Staging claim is made.
