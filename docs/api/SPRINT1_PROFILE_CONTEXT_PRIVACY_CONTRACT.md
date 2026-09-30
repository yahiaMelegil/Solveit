# SolveIt Sprint 1 API contract

Version: 1.0.0. Status: fixed additive contract; locally verified backend, approved architecture on 2026-09-29. Backend verification and React/Staging acceptance are separate gates. This is the new Core Profile/Context/Privacy Sprint 1, not the historically named Expert eligibility delivery.

## Boundaries and explicit decisions

Separate User/Expert/Admin accounts and existing responses stay unchanged. This profile/privacy contract does not implement Case Core, account linking, payments, credential renewal, appeal or irreversible account deletion. Credential renewal is now provided by the separate additive [renewal contract](SPRINT1_EXPERT_RENEWAL_CONTRACT.md), approved2026-09-30. Existing UserResource and KYC contracts are not expanded. Profile and Context retain immutable application-level versions. A context is private, has one immutable domain/country, and never grants an Expert access. A future Case must select a version and reauthorize its purpose.

Export produces an authenticated, expiring JSON artifact for the current User account only. No other account is included, even with the same email. Deletion requests are real persisted, auditable requests, automatically deferred for an approved retention/execution policy. They do not delete, anonymize, disable the account, or claim completion. Admin endpoints are read-only. No operational decision endpoint is introduced.

Published legal policy text is not supplied. Production policy records must be explicitly provisioned with approved text, version, locale, purpose and effective date. No implicit consent, fake acceptance, or production legal-text seed. A development/testing seeder includes clearly labeled synthetic examples and refuses other environments. Recording preference does not authorize a session recording. AI assistance is separate from AI training; training is disabled. Marketing requires a separate current purpose-specific consent. Essential security/service email is independent of optional channels.

Default operational settings: export artifact TTL 24 hours, idempotency retention 24 hours, reauthentication validity 5 minutes. These are technical defaults, not legal retention periods. Data-request due days have no invented legal default: the deployment owner must configure an approved positive operational SLA in config/data_rights.php. New requests fail with POLICY_CONFIGURATION_REQUIRED until configured. Existing unverified-user authentication remains unchanged; an alternative identity-confirmation/data-rights assistance procedure is a deployment policy dependency.

## Authentication, authorization and response conventions

U = auth:sanctum, regular-user, user.verified, abilities:user:access. A = auth:sanctum, admin, abilities:admin:access and the listed permission (checked before validation and with Gate::authorize in the controller). R = recent password confirmation for the exact User personal access token and purpose. All ownership is taken from the token; unknown and foreign IDs return the same 404. User mutation bodies reject unknown keys, including user_id and status. Administrative routes never expose Context content, phone, policy text, tokens or internal file paths.

All dates/times are UTC ISO 8601; effective dates are YYYY-MM-DD. New JSON success responses: {status:true,message:string,data:{item:Resource}}; lists: data.items and data.pagination {currentPage,perPage,lastPage,total}. Metadata catalog endpoints may use data.items without pagination. Errors: {status:false,code:string,message:string,errors?:{field:[message]}}. Existing API envelopes are preserved.

## Endpoint inventory

All URLs below have the /api prefix. All User routes have U and read/write throttling. Permissions are none for U (ownership Policy applies).

| Method | URL | Route name | Auth/permission | Request | Success |
|---|---|---|---|---|---|
| GET | /user/profile | user.profile.show | U | none | 200 Profile |
| PATCH | /user/profile | user.profile.update | U | profile patch + expectedVersion | 200 Profile |
| GET | /user/preferences | user.preferences.show | U | none | 200 Preferences |
| PATCH | /user/preferences | user.preferences.update | U | preferences patch + expectedVersion | 200 Preferences |
| GET | /user/context-schemas | user.context-schemas.index | U | none | 200 schema catalog |
| GET | /user/contexts | user.contexts.index | U | list query | 200 paginated Context summaries |
| POST | /user/contexts | user.contexts.store | U | context + Idempotency-Key | 201 Context |
| GET | /user/contexts/{context} | user.contexts.show | U | none | 200 Context |
| PATCH | /user/contexts/{context} | user.contexts.update | U | context patch + expectedVersion | 200 Context |
| GET | /user/contexts/{context}/versions | user.contexts.versions.index | U | list query | 200 paginated ContextVersion |
| POST | /user/contexts/{context}/archive | user.contexts.archive | U | expectedVersion + Idempotency-Key | 200 Context |
| POST | /user/contexts/{context}/restore | user.contexts.restore | U | expectedVersion + Idempotency-Key | 200 Context |
| DELETE | /user/contexts/{context} | user.contexts.destroy | U | expectedVersion + Idempotency-Key | 200 Context tombstone |
| GET | /user/policies | user.policies.index | U | none | 200 current policy versions |
| GET | /user/consents | user.consents.index | U | none | 200 purpose summaries |
| GET | /user/consents/history | user.consents.history | U | list query | 200 paginated ConsentRecord |
| POST | /user/consents | user.consents.store | U | consent decision + Idempotency-Key | 201 decision, 200 unchanged decision |
| POST | /user/security/confirm-password | user.security.confirm-password | U | currentPassword, purpose | 200 confirmedUntil |
| GET | /user/data-requests | user.data-requests.index | U | list query | 200 paginated DataRequest |
| POST | /user/data-requests | user.data-requests.store | U+R | type, scope + Idempotency-Key | 202 DataRequest |
| GET | /user/data-requests/{dataRequest} | user.data-requests.show | U | none | 200 DataRequest |
| POST | /user/data-requests/{dataRequest}/cancel | user.data-requests.cancel | U | expectedVersion + Idempotency-Key | 200 DataRequest |
| GET | /user/data-requests/{dataRequest}/download | user.data-requests.download | U+R export | none | 200 application/json attachment, private/no-store |
| GET | /admin/users/{user}/consents | admin.users.consents.index | A + users.consentMetadata.view | list query | 200 paginated metadata |
| GET | /admin/data-requests | admin.data-requests.index | A + dataRequests.viewAny | list query | 200 paginated metadata |
| GET | /admin/data-requests/{dataRequest} | admin.data-requests.show | A + dataRequests.view | none | 200 metadata |

## Validation

PATCH requires expectedVersion integer >=0 and at least one mutable field. A stale version returns 409 VERSION_CONFLICT. Normalized no-op updates retain the version. Writes lock the owner and aggregate and use explicit allowlists. Reject unknown nested keys too.

Profile: name non-empty trimmed string max255; phone nullable E.164-shaped + and 8..15 digits, no verification/OTP claim; country nullable ISO alpha-2 code; language nullable ar/en; timezone nullable valid IANA name max64. Email/password/account IDs cannot be changed. Name remains an unverified account display/full name, not a verified legal identity. A first write preserves snapshot version 0; GET does not create rows. Version snapshots include name, phone, country, language, timezone but no password/token.

Preferences: contactChannels distinct array containing only email (empty is allowed); aiAssistanceEnabled boolean; recordingPreference boolean; contextVisibility=private only. AI/recording preferences express intent, not an authorization grant. No downstream AI or recording execution exists in this sprint. Marketing is derived from current Consent, never a second independently editable boolean.

Context create: domain from context_schemas.domains; country ISO alpha-2; schemaVersion=1; title trimmed 1..160; facts array 1..20. Each fact: key from domain field allowlist, value string 1..2000, source string 1..120, effectiveDate YYYY-MM-DD not in the future. Keys are distinct. allowCaseReuse optional boolean default false. Payload limit is bounded by these rules. Initial domains are non-regulated pilot categories only; legal/medical/health/finance are not enabled by this catalog. Domain schemas are versioned product templates, not a professional eligibility catalogue. Context patch allows title/facts/allowCaseReuse/clarification (max1000), not domain/country. Changed values for a stable fact key at the same effectiveDate flag unresolved conflict; both snapshots are retained. A clarification creates a new clarified version, never rewrites old evidence. Any unresolved conflict remains until explicit clarification. Archived contexts require restore before content edits. Logical deletion creates a private tombstone and excludes payload from the response; actual erasure uses the separate data-rights policy. No restoration of deleted contexts in this contract.

Consent: purpose from terms/privacy/marketing/ai_assistance; policyVersionId positive integer; decision granted/declined/withdrawn; previousRecordId required on withdrawal. Grant/decline must target the current effective published policy for that purpose/locale. Withdrawal must target the owner's latest active grant of the same purpose and version; it remains possible after a policy update. terms/privacy acceptance is not withdrawn via an optional-consent command: use a separate account/data-rights workflow. No grant is inferred from registration. purpose-specific current acceptance and policy hash are exposed. Material change returns requiresReconsent rather than rewriting prior decisions. Policy locale is an explicitly selected published text, not silently replaced on profile language change.

Password confirmation: currentPassword required string, validated against the authenticated User; purpose export or deletion. Stored confirmation is cache-backed, token-specific, purpose-specific and expires. No password is logged, persisted, hashed into an idempotency record or queued. Expired proof is 403 REAUTHENTICATION_REQUIRED. Account credentials never travel in GET/query parameters.

Data request: type export/deletion; scope=account only in v1 (contains current User data, not Expert/Admin data). Types/scope/status cannot be patched. One open request per User/type, including failed requests awaiting worker retry, prevents duplicates. Other submitted keys fail validation. Due date is frozen from approved config at creation. User cannot supply it. The first version is 1. The signed-in user can cancel requested/deferred/failed; processing/completed/rejected/cancelled are not cancellable (repeating the same idempotency key is safe).

## Errors and rates

| HTTP | Codes | Meaning |
|---|---|---|
| 401 | UNAUTHENTICATED | Missing/invalid/revoked token |
| 403 | FORBIDDEN, EMAIL_NOT_VERIFIED, REAUTHENTICATION_REQUIRED | Wrong account/ability/permission or expired confirmation |
| 404 | RESOURCE_NOT_FOUND | Absent/foreign object, no ownership leak |
| 409 | VERSION_CONFLICT, INVALID_STATE_TRANSITION, DUPLICATE_OPEN_REQUEST, IDEMPOTENCY_CONFLICT, POLICY_VERSION_CHANGED, POLICY_CONFIGURATION_REQUIRED, EXPORT_NOT_AVAILABLE | Stale state/configuration or unavailable artifact |
| 422 | VALIDATION_FAILED | Invalid/unknown fields, unsupported schema, invalid password/policy |
| 429 | RATE_LIMITED | Rate exceeded; Retry-After preserved and exposed through CORS |
| 500 | INTERNAL_ERROR | Generic server failure; correlate through X-Request-ID |

Limits: user/admin reads 120/minute, general writes 30/minute, confirmation 5/minute, data-request creation 5/hour, downloads 10/minute. Rate-limit keys include account class/id; password proof is additionally bound to the token. Password confirmation also has an IP rate limit. Unauthorized access is checked before private lookup/validation. No raw exception, path or provider response leaks into public errors.

## Lists

page integer >=1; perPage integer 1..100 default20; sortDirection asc/desc defaultdesc. Stable ID tiebreaker. Context filters status active/archived (default excludes deleted), domain, country; sortBy updatedAt/default or createdAt. Versions sortBy version. Consent history filters purpose, policyVersionId; sortBy decidedAt. Requests filter type/status and optionally userId for permitted Admin list; sortBy requestedAt/default or dueAt. Unknown query keys and SQL column names are rejected. No private-content search or admin context-content endpoint.

## States and transactional behavior

Profile/Preferences n -> n+1 only on actual change. Context active -> archived -> active; active/archived -> deleted. Every actual context mutation has a new immutable version including lifecycle/reuse metadata. canUseInFutureCase = active && opted-in && no unresolved conflict && enabled domain/country, and is not a Case access grant.

Consent decisions are append-only. Current consent is derived from the latest event, the current policy and requires_reconsent. Essential communication cannot be disabled by marketing withdrawal.

Requests: requested -> processing/cancelled; processing -> completed/rejected/deferred/failed; deferred -> processing/cancelled; failed -> processing/cancelled. Terminal states do not restart. Workers retry failed requests with bounded attempts. Export completed requires a real private artifact and checksum. Deletion worker transitions to deferred with RETENTION_POLICY_PENDING and all relevant item outcomes deferred; no fake completed path. Unexpected processing failure produces failed with a safe reason and can be retried. No public status mutation endpoint.

Audit writes commit atomically with state changes. Events contain identifiers, version/state changes and reason codes, not payload snapshots. User mutation history references encrypted snapshots, not duplicated plaintext. New Admin metadata reads and sensitive download attempts are audited. Jobs carry request IDs only, use afterCommit dispatch and check eligibility again. Worker and cleanup failures must not be represented as success. Expired worker leases are fenced and recovered to failed by cleanup; an infrastructure operator can requeue requested/failed using privacy:retry. Rejected is a reserved state, with no decision endpoint in this release.

## Idempotency and concurrency

Idempotency-Key required for the POST/DELETE mutations marked above; ASCII 16..128 characters. Key scope = account type/id + route operation + target. Canonical validated input is hashed. Same key/body replays the original result (which may be historical; clients refresh after replay), same key/different body is 409. Password proof is rechecked before sensitive replays. Only successful domain results are recorded. Rows expire after 24h; independent open-request checks remain effective after expiry. Stored response bodies, where needed for exact replay, are encrypted and never include credentials or artifacts. All create paths lock an existing User row first to serialize the no-row-yet race. Aggregate transitions use lockForUpdate plus expectedVersion. No file I/O is performed while holding request locks for an entire export.

## Export format and retention

JSON format solveit.account-export.v1 includes manifest {generatedAt,scope,formatVersion,sections,exclusions}, account {id,name,email,createdAt}, profile/current+versions, preferences, contexts+versions, consent decisions with policy version/hash, own data-request summaries. Excludes passwords, remember tokens, access/reset tokens, audit security metadata, other accounts, KYC, nonexistent Case data. Artifact content is encrypted at application storage boundary, and downloaded as JSON only through an owner-authorized endpoint. Export is a documented best-effort account snapshot with a capture timestamp; immutable profile/context versions are named. No claim of cross-domain transactionally frozen future Cases.

downloadAvailable is a metadata hint; missing/corrupt storage can still return 409. Download requires current ownership, completed export, fresh export confirmation, existing unexpired artifact and checksum; headers Cache-Control private,no-store and Content-Disposition attachment. No public URL is returned. Cleanup deletes expired artifacts and clears internal paths after success. If storage deletion fails it is retried and expiry still blocks download. Full deletion/history/audit retention and backup erasure remain policy dependencies; no blanket cascade purge of evidence is scheduled.

## Field mapping and fixtures

See docs/sprint1/core-profile-context-privacy/DATABASE_MAPPING.md, PERMISSION_MATRIX.md, STATE_TRANSITIONS.md, FRONTEND_HANDOFF.md and fixtures/*.json. CHANGELOG records v1.0.0 additive endpoints. Fixture policy texts are synthetic, not approved legal policies. Fixture status examples describe contract states and never imply a live external deployment.
