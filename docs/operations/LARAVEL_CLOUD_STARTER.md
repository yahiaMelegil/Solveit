# Laravel Cloud deployment decisions for the current backend

Decision date: 2026-09-28. This is a plan, not a change to the deployed environment or `.env`.

## Recommended baseline

1. **Database:** keep the current production database engine and data. If production is already MySQL, use managed Laravel MySQL in the same region as the app; do not switch engines inside Sprint 0. The schema has MySQL-oriented `after()` migrations and has not been executed here against PostgreSQL. Keep SQLite `:memory:` only for the existing test suite. Verify the Cloud dashboard's actual attached database and test a restored/sanitized copy before any migration.
2. **Compute:** start with one Flex app replica and scale to zero only when its latency is acceptable to the pilot. Set an organization spending limit and monitor usage. Do not assume a zero subscription price: Laravel Cloud's public Starter pricing was $5/month plus usage, with $5 monthly usage credit and first month free on 2026-09-28. The user's actual account terms are authoritative.
3. **Private KYC files:** use durable private S3-compatible Object Storage with authenticated server-side downloads. Laravel Cloud environments have ephemeral local filesystems; current `kyc` uses `storage/app/private/kyc` and is unsafe as a durable production source. Do not point the current `KYC_FILESYSTEM_DISK` at `s3` yet: the lock file lacks `league/flysystem-aws-s3-v3`, and the existing object storage code has not been integration-tested. Package addition and credential/configuration changes require a separate approved deployment step. Keep all existing private files accessible until verified transfer and checksum reconciliation succeeds.
4. **Avatar files:** current `ExpertProfileManager` hardcodes the local `public` disk. Move these to a durable public object-storage delivery path in a separately tested migration; an environment variable alone cannot change this code. Do not silently invalidate existing avatar URLs.
5. **Queue:** a managed Flex queue is available on Starter (one per environment). Attach it before using asynchronous KYC decision notifications; ensure the queue connection, worker, retries, and failed-job dashboard are verified. The present `config/queue.php` defaults to database, and only local tests use `sync`. Admin invitation mail is sent synchronously and may fail a request when mail delivery fails; this needs an operational decision and test.
6. **Mail:** verify a real sending provider and domain, then test verification, password reset, invitation, and KYC notification delivery. `MAIL_MAILER=log` is a development fallback, not user delivery. Never put credentials in this document.

## Release checks

- Back up the existing database; confirm a restore on a nonproduction environment.
- Verify a private KYC upload survives redeploy and can be downloaded only by the owner/reviewer; verify checksum and delete behavior.
- Verify public avatar URLs survive redeploy.
- Verify a queued notification completes and a failing one reaches the failed-job view.
- Run `composer validate --strict`, `composer install`, `php artisan about`, `php artisan route:list --path=api`, `php artisan test`, and `vendor/bin/pint --test` outside production. Run migrations only against an isolated, disposable or restored test database before a planned production release.

## Deferred provider decisions

Payment/payout provider and legal funding wording, Realtime, video, AI, malware scanning, and ID verification require explicit provider and policy selection before their dependent features. Do not use the term **escrow** until the selected legal/provider model supports it (SRS section 3.3). This document makes no claim that those providers are configured.

Sources checked 2026-09-28: [Laravel Cloud pricing](https://laravel.com/cloud/pricing), [ephemeral environments](https://laravel.com/cloud/docs/environments), [managed queues](https://laravel.com/cloud/docs/queues), [object storage](https://laravel.com/cloud/docs/resources/object-storage), and [Laravel filesystem S3 prerequisites](https://laravel.com/docs/13.x/filesystem). Recheck limits and account-specific charges before provisioning.
