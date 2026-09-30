# React handoff — contract 1.0.0

عقد الباك إند مثبت في هذا التسليم. لم يتم اختبار React أوStaging. يبدأ ربط Sprint 1 بعد إغلاق واجهات Sprint 0 والتحقق من توافقها. لا تغيّر عقود Auth/KYC/RBAC القائمة لتطبيق هذه الواجهات.

1. أرسل Bearer token لحساب User مع user:access بعد تأكيد البريد. افصل تخزين وسياق توكنات User/Expert/Admin كما في Sprint 0.
2. اقرأ profile وpreferences؛ غياب صف مخزن يعيد version=0 دون إنشاء بيانات. أرسل فقط الحقول المتغيرة وexpectedVersion. email وphoneVerified للقراءة فقط؛ الهاتف غير موثق ولا يدعم SMS.
3. اقرأ context-schemas قبل بناء النموذج؛ اعرض مفاتيح facts الخاصة بالمجال. country/domain ثابتان بعد الإنشاء. لا تضع بيانات حالات متعددة تلقائيًا في Context واحد. allowCaseReuse اختيار صريح، وcanUseInFutureCase لا ينشئ Case.
4. اعرض unresolved واحتفظ بالإصدارات؛ اطلب توضيحًا صريحًا. أرشفة السياق قابلة للاستعادة؛ حذف السياق منطقي ويخفي المحتوى من الواجهة، ولا يعد محوًا للسجل التاريخي.
5. اقرأ policies واحفظ id/version/locale للنص المعروض. لا تستخدم سياسة ثابتة داخل React ولا تستنتج موافقة من التسجيل. اعرض not_recorded وrequiresReconsent، واجعل marketing اختيارًا مستقلًا. لا تربط essential email بخيار التسويق.
6. لإنشاء طلب تصدير/حذف: POST confirm-password مع purpose نفسه ثم POST data-requests خلال5 دقائق بالتوكن نفسه. أرسل كلمة المرور داخل جسم طلب HTTPS فقط، ولا تحفظها في logs/storage/analytics. التنزيل يحتاج تأكيد export صالحًا أيضًا.
7. requested/processing تعني قيد العمل. deferred للحذف تعني مؤجلًا دون تنفيذ؛ اعرض reasonCode وdueAt وreviewAt. لا تعرض رسالة «تم حذف الحساب». completed للتصدير يسمح بمحاولة تنزيل JSON عبر endpoint محمي. downloadAvailable مؤشر metadata؛ قد يعيد التنزيل409 عند فقد الملف أو تلفه. cancelled/rejected/failed حالات مختلفة.
8. الأدمن يرى metadata فقط بناءً على إحدى الصلاحيات الثلاث المستقلة. لا تضف روابط لتنزيل صادرات المستخدمين أوعرض Context خاص بالأدمن.

## Requests, retries and dates

API names use camelCase; all times UTC ISO8601. Display in the user's timezone; stored language/timezone preferences do not introduce a new translated notification catalog or scheduler in this sprint. Null optional values are explicit; omitted PATCH fields stay unchanged. Booleans should be JSON true/false and IDs/versions JSON numbers. No user_id, role, dueAt, status or arbitrary nested keys.

Generate one UUID Idempotency-Key per logical marked POST/DELETE action; reuse it after network retry with exactly the same body/target. A different intended action uses a new key. Replay retains the original HTTP status/body and includes Idempotency-Replayed:true. Reauthentication is rechecked before sensitive replays. PATCH uses expectedVersion, so after an uncertain response read the latest state before another edit. Never auto-resubmit a stale change on409.

Paginated lists: data.items + data.pagination {currentPage,perPage,lastPage,total}; defaults 20, maximum 100. Catalogs and consent summaries are finite non-paginated arrays. Data-request list entries omit items; GET detail includes them. Context list entries omit facts/clarification. See contract for allowlisted filters and sorting. There is no free-form private-content search.

| HTTP/code | UI behavior |
|---|---|
|401 UNAUTHENTICATED|Existing login flow; discard invalid token|
|403 EMAIL_NOT_VERIFIED|Existing verification screen|
|403 FORBIDDEN|Do not expose another account's area|
|403 REAUTHENTICATION_REQUIRED|Confirm password for the required purpose|
|404 RESOURCE_NOT_FOUND|Generic unavailable; do not distinguish foreign IDs|
|409 VERSION_CONFLICT|Reload; ask user to reconcile changes|
|409 POLICY_VERSION_CHANGED|Reload and display current policy before a new decision|
|409 DUPLICATE_OPEN_REQUEST|List current open request; do not create loops|
|409 POLICY_CONFIGURATION_REQUIRED|Service setup incomplete; show actionable support message|
|409 EXPORT_NOT_AVAILABLE|Refresh status; request a new export if expired/unreadable|
|422 VALIDATION_FAILED|Bind errors[field] to form fields|
|429 RATE_LIMITED|Honor Retry-After; avoid automatic rapid retries|
|500 INTERNAL_ERROR|Generic retry/support message; retain X-Request-ID for support|

CORS exposes Retry-After, Idempotency-Replayed, X-Request-ID, Content-Disposition. It allows Idempotency-Key. Download via authenticated fetch and a local Blob/object URL; revoke it afterward. Never construct storage URLs. Do not persist Profile/Context/export content to shared browser caches.

## Fixtures and acceptance

fixtures/*.json contain request method/URL/body and actual response status/headers/body captured by ContractFixturesTest. They use synthetic Mariam Hasan/مريم حسن, example.com, synthetic policy text and a30-day TEST SLA. Tokens/passwords are placeholders. UUIDs are sample IDs, not reusable live resource identifiers. request-rejected-reserved.json is explicitly an internally seeded future rendering state. Regenerate with SOLVEIT_FIXTURE_DIR=... vendor/bin/phpunit tests/Feature/Privacy/ContractFixturesTest.php against the test environment only.

Before frontend acceptance: verify real Staging base URL/CORS, Sprint0 login+verification+logout/regression, profile stale edits, context conflict/archive/delete, optional consent withdrawal/material reconsent, request duplicate/cancel, worker-produced artifact/download/expiry, reauth per token, Admin permission revocation and429. Confirm actual policy texts/SLA/catalog with product/legal owners. Frontend integration and end-to-end completion remain open until these checks run against React.

## Expert renewal addition — 2026-09-30

Renewal is now part of Sprint1 under [its additive contract](../../api/SPRINT1_EXPERT_RENEWAL_CONTRACT.md). Expert/Admin React handoff and fixtures are in [the renewal release](../expert-renewal/README.md). User privacy endpoint shapes stay unchanged.
