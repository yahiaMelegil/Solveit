# Authentication and authorization merge

## Summary

This change set fixes regular-user email-verification enforcement and merges
administrator RBAC into the latest backend without replacing its KYC or Expert
Verified Scope implementation.

## Deployment order

1. Back up the application database.
2. Copy the delivered files while preserving their paths.
3. Install and lock the new dependency:

   ```bash
   composer update spatie/laravel-permission --with-all-dependencies
   ```

4. Clear cached configuration and discovered services:

   ```bash
   php artisan optimize:clear
   ```

5. Create the permission tables:

   ```bash
   php artisan migrate --force
   ```

6. Create/update the permission catalog and default roles:

   ```bash
   php artisan db:seed --class=AuthorizationSeeder --force
   ```

7. If the initial administrator has not received `super_admin`, configure
   `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`, then run:

   ```bash
   php artisan db:seed --class=AdminSeeder --force
   ```

8. Reset the permission cache and run tests:

   ```bash
   php artisan permission:cache-reset
   php artisan test
   ```

9. Run the formatter and inspect routes:

   ```bash
   vendor/bin/pint --test
   php artisan route:list --path=api
   ```

The supplied workspace did not contain a PHP or Composer executable, so the
lock file could not be regenerated and PHP tests could not be executed while
building this archive. Do not deploy before running the commands above in the
target PHP environment and committing the regenerated `composer.lock`.

## Behavioral changes

- Registration returns a `user:verify-email` token, not `user:access`.
- Unverified login returns HTTP 403 with `code: EMAIL_NOT_VERIFIED` and a
  verification-only token.
- Invalid credentials return HTTP 401 with `code: INVALID_CREDENTIALS`.
- `/api/user` dynamically requires a verified email and `user:access`.
- Admin responses include roles and effective permissions when those relations
  are loaded.
- Admin management and role APIs are protected through policies and Gates.
- KYC review additionally requires `experts.reviewKyc`.

## Rollback

Application rollback should restore the previous versions of the modified
files and remove the files marked `Added` in `CHANGED_FILES.md`. Only after the
old application code is restored may the RBAC tables be removed by rolling back
the permission-table migration. Take a database backup first; rolling back that
migration deletes all role and permission assignments.
