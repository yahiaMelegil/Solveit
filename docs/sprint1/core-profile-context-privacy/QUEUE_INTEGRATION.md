# Local queue integration verification — delivery revision 2

A separate-process integration test now verifies the operational boundary that the earlier direct job tests did not cover. The API contract remains1.0.0; no HTTP fields, endpoints, permissions, migrations or runtime application behavior were changed by this follow-up.

Run:

```bash
vendor/bin/phpunit tests/Feature/Privacy/QueuedPrivacyIntegrationTest.php --display-warnings
```

Actual result on2026-09-29: **1 test,51 assertions, passed**. Full suite after this addition: **211 tests,1333 assertions, passed**. Privacy-focused suite: **55 tests,469 assertions, passed**.

## What runs

The test creates a private disposable temporary directory, SQLite database, database-backed cache, database queue and private export disk. It migrates that fresh database and creates synthetic User/Expert/Admin accounts. The30-day SLA and example policies belong to the disposable test only. No project .env or deployment configuration is changed.

HTTP requests pass through the real Laravel HTTP kernel in separate PHP processes. The test then executes the actual `artisan queue:work database --once` command in another process. It does not use Queue::fake, Storage::fake or direct Job::handle invocation. A compiled test configuration shares only the isolated test resources, and the actual route:cache command is exercised against a temporary route-cache file. This is not a test of the production config:cache deployment command.

Assertions cover:

1. Password confirmation persists in the database cache and is recognized by the next process using the same token/purpose.
2. An export request commits as requested and produces exactly one queued job. Repeating the same idempotency key replays the response without another job.
3. The serialized job payload excludes account email, currentPassword and the bearer token.
4. The separate queue worker completes the request, drains the queue, records the processing/completed audit transitions and leaves no failed job.
5. The real stored artifact is encrypted and its decrypted checksum matches the recorded checksum.
6. A later authenticated request downloads the correct account JSON with private/no-store and attachment headers.
7. Another User receives404; Expert and Admin receive403; another token for the same User receives403 until separately confirmed.
8. A queued deletion request reaches deferred with RETENTION_POLICY_PENDING and retains the User.
9. Expiry blocks download with409; the actual privacy:cleanup command removes the expired artifact while preserving the account.

Tokens pass only over private child-process input/output; test failures suppress raw child output. All temporary database/config/cache/artifact files are removed in the test's finally block. The helper under tests/Support is for the integration test and must not be exposed as a web endpoint.

## Limits

This verifies a local database queue, cache and filesystem across PHP processes on SQLite. It does not verify HTTP network/proxy behavior, browser CORS, React, Staging, the production database engine, Redis/SQS, multi-host storage, simultaneous row-lock races or legal erasure. Those acceptance gates remain open. No deletion executor, renewal workflow or legal policy was invented.
