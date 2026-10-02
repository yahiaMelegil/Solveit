# Decisions and explicit limitations
- Owner authorized implementation now and explicitly deferred the existing Privacy ContractFixturesTest time fix.
- Keep four approved Case states. Suitability/scan outcomes are separate enums, not extra Case states.
- Case ready is frozen except cancel; no reopen workflow.
- Country service eligibility is empty until product approval. Existing country catalog only validates syntax.
- Recognized regulated domains can be described in drafts but are unsupported for this intake release;
  no ADR-002 expansion or medical/legal decision workflow is inferred.
- Suggestions are simple bilingual keyword rules, not AI, not calibrated confidence, not professional advice.
- Human triage routing is configuration only; no admin triage queue was invented. The SRS operational triage
  requirement remains pending an approved staffed channel. Unsupported/urgent cases cannot become ready.
- Third-person cases can be saved but cannot submit. Representation/consent workflow remains pending approval.
- Private documents have encrypted byte storage, server MIME/extension/size checks, checksum and revisions.
  ClamAV integration is implemented; actual scanner/signature provisioning is an environment gate. No full-text
  extraction/OCR/content indexing or PDF rendering is delivered. Metadata listing is available; do not claim full
  SRS content indexing. PDF preview returns409; authorized attachment download is supported after clean scan.
- Snapshot authorization is case-specific. Snapshot content is immutable; reuse withdrawal/archive blocks
  future submit, without silently changing/removing history from already submitted cases.
- Draft retention deadline and automatic deletion notifications are not invented. No irreversible erasure.
  Document removal and context detachment are logical; retained revisions remain private and inaccessible
  through removed-document endpoints. Cleanup deletes only unreferenced temporary storage objects.
- Existing account-export v1 keeps its sections. Exclusion reason now truthfully states case_data_outside_export_v1.
  Exporting all Case data/files requires a separate extension, not falsely asserted as already covered.
- No PHPStan/Psalm configuration/package was present. PHP syntax checks and Pint are run; neither is represented
  as full type/static analysis. Adding a new analysis dependency remains a separate toolchain decision.
- Actual production-engine concurrency, scanner deployment, staging and React acceptance remain unverified.
