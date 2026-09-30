# Database and API mapping

One additive migration creates 11 tables. There are no new columns in users/experts/admins. No duplicate existing profile/privacy design was found in the supplied baseline. No existing migration is edited.

## Profile and preferences

| API | Storage / source | Rules / exposure |
|---|---|---|
| name | users.name; snapshots in user_profile_versions.snapshot | Existing account display name. Self-editable, unverified; no legal-identity claim |
| email | users.email | Read only here; existing verification and reset flows unchanged |
| phone | user_profiles.phone | Nullable encrypted text, + and 8..15 digits; phoneVerified always false |
| country | user_profiles.country | Nullable ISO alpha-2, normalized uppercase |
| language | user_profiles.language | Nullable ar/en preference; policy locale remains explicit |
| timezone | user_profiles.timezone | Nullable IANA timezone; API timestamps remain UTC |
| version / expectedVersion | user_profiles.version | Initial virtual 0; optimistic precondition on PATCH |
| updatedAt | user_profiles.updated_at | Null until first actual change |
| contactChannels | user_preferences.contact_channels JSON | email or empty; optional channel selection |
| essentialChannels | Resource constant [email] | Not writable; authentication/security/service messages remain essential |
| aiAssistanceEnabled | user_preferences.ai_assistance_enabled | Intent only; requires independent effective consent before any future processor |
| recordingPreference | user_preferences.recording_preference | Intent only; no recording authorization/execution |
| contextVisibility | user_preferences.context_visibility | private only |
| aiTrainingEnabled | Resource constant false | Not writable |
| preferences.version | user_preferences.version | Virtual 0 until actual change |

users 1:0..1 user_profiles and user_preferences (unique user_id FK). users 1:N user_profile_versions. Snapshot fields: user_id, version, encrypted snapshot {name,phone,country,language,timezone}, changed_fields JSON, created_at. Unique (user_id,version). The first write captures version 0 as well as version 1. GET has no persistence side effects. Existing users.name remains plaintext as in Sprint 0; newly captured phone/context/version data is encrypted. Email/phone/name and context narratives must be treated as personal data. Phone is neither an authentication factor nor a verified contact channel.

## Contexts

| API | Storage | Notes |
|---|---|---|
| id, domain, country, status | specialized_contexts | user_id derived from token; domain/country immutable |
| version / expectedVersion | specialized_contexts.current_version | Starts at1; all actual changes append a version |
| allowCaseReuse | specialized_contexts.allow_case_reuse | Defaults false, independent of status |
| schemaVersion | specialized_context_versions.schema_version | Currently1 |
| title, facts | specialized_context_versions.payload | Encrypted JSON cast stored as longText |
| facts[].key/value/source/effectiveDate | payload.facts | Domain-key allowlist, bounded values, dates <=today |
| conflictStatus | specialized_context_versions.conflict_status | none/unresolved/clarified |
| clarification | specialized_context_versions.clarification | Nullable encrypted text |
| supersedesVersion | specialized_context_versions.supersedes_version | Previous version number; history is retained |
| createdAt | Aggregate or version created_at | UTC |
| canUseInFutureCase | Derived | Active, opted in, latest version exists, no unresolved conflict, enabled domain and country |
| visibility | Resource constant private | No Expert or public endpoint |

users 1:N specialized_contexts; contexts 1:N specialized_context_versions. Snapshot payload also freezes domain/country/status/allowCaseReuse. Unique (context_id,version). Indexes: (user_id,status,updated_at,id), (user_id,domain,status). Logical deletion marks status/deleted_at and returns a payload-free tombstone; it does not erase historical content. Deleted histories remain part of the owner's account export. Latest versions are eager loaded for lists; no per-item version query.

## Policies and consent

| API | Storage | Notes |
|---|---|---|
| policy.id/policyVersionId | policy_versions.id | Stable FK |
| policyKey | policy_versions.policy_key | Stable policy family key |
| purpose | policy_versions.purpose and user_consent_records.purpose | terms/privacy/marketing/ai_assistance |
| version/policyVersion | policy_versions.version | Explicit publisher version string |
| locale/policyLocale | policy_versions.locale | Explicit text locale |
| content | policy_versions.content | Exact approved text; never exposed in Admin metadata |
| contentHash/policyHash | policy_versions.content_hash | SHA-256 of exact stored UTF-8 content |
| effectiveAt | policy_versions.effective_at | Future/unpublished versions cannot be accepted |
| requiresReconsent | policy_versions.requires_reconsent; derived summary | Material policy revision invalidates earlier effective grant |
| consent.id/recordId | user_consent_records.id | Append-only decision |
| decision | user_consent_records.decision | granted/declined/withdrawn |
| previousRecordId | user_consent_records.previous_record_id | Links decision chain; withdrawal checks current owner's grant |
| decidedAt | user_consent_records.decided_at | Server timestamp |
| effective/withdrawable | Derived summary | No stored mutable consent boolean |

Unique policy (policy_key,version,locale); effective-policy index (purpose,locale,is_published,effective_at). Consent FKs point to User, PolicyVersion and optional previous decision. Indexes (user_id,purpose,decided_at,id), (user_id,purpose,id). source=api and server request_id are audit metadata, not client input. Policy/consent/history model writes cannot update or delete existing rows; direct SQL permissions and backup retention remain operator responsibilities.

## Data requests

| API | data_rights_requests column / source |
|---|---|
| id, reference | id; unique UUID reference |
| type, scope, status, version | type export/deletion; scope account; enum status; version |
| requestedAt, dueAt | requested_at, due_at (frozen approved SLA) |
| startedAt, completedAt | started_at, completed_at; terminal timestamp also applies to cancelled/rejected |
| reasonCode, outcome | reason_code; minimal JSON outcome |
| downloadExpiresAt | artifact_expires_at |
| downloadAvailable | Metadata-derived availability hint; endpoint also verifies storage and checksum |
| items[].recordClass/status/reasonCode | data_rights_request_items.record_class/status/reason_code |
| items[].retainUntil/reviewAt | retain_until/review_at; no invented legal deadline |

Private columns: user_id, identity_confirmed_at, artifact_disk/path/checksum/size, processing_token/processing_lease_until, created_at/updated_at. Only user_id appears in separately permissioned Admin metadata. No public artifact URL/path or processing token.

users 1:N requests; requests 1:N items. Unique (request_id,record_class). Request indexes: (user_id,type,status), (user_id,requested_at,id), (status,due_at,id), (status,processing_lease_until), artifact_expires_at; items (request_id,status). One-open-request enforcement serializes on an existing User row within a transaction. This is not a portable partial unique constraint; target production DB concurrency must be tested. All writers must use the service.

## Audit and idempotency

Audit fields: event_id unique UUID, actor_type, actor_id, actor_token_id (numeric ID only), action, subject_type/id, previous_state/new_state, reason_code, request_id UUID, occurred_at, metadata JSON. Indexes (subject_type,subject_id,occurred_at), (actor_type,actor_id,occurred_at). actor_type distinguishes User/Admin/Expert/system; actor references intentionally survive token removal and are not a unified account identity. No content/password/token/IP/user-agent is persisted here.

Idempotency fields: actor_type/id, operation(route+target), key_hash SHA-256, request_hash SHA-256 of canonical validated input, encrypted response_body, response_status, expires_at. Unique (actor_type,actor_id,operation,key_hash); expiry index. Credentials are never accepted into this path. Responses expire after 24h and are cleaned by privacy:cleanup.

All data-bearing foreign keys restrict deletion, including versions/evidence. No cascade purge or automatic history deletion. Encryption uses existing Laravel APP_KEY with encrypted casts/Crypt; key backup and rotation must preserve decryption. Search indexes cover metadata only, not encrypted narratives.
