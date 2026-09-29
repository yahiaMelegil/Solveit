# Existing API route inventory — static Sprint 0 baseline

Source: `routes/api.php` in the 2026-09-28 archive. The backend owner's later Windows `php artisan route:list --path=api` output confirms **67 registered route entries**; `admin.admins.update` accepts both PUT and PATCH, giving 68 method/path combinations if counted separately. This inventory combines that pair in one row. All paths below start with `/api`. No endpoint was added or removed by Sprint 0.

Legend: `U` = `auth:sanctum,regular-user`; `E` = `auth:sanctum,expert,abilities:expert:access`; `EV` = E plus `expert.verified`; `A` = `auth:sanctum,admin,abilities:admin:access`; `AK` = A plus `can:experts.reviewKyc`. `P` = public (some public routes have `signed` or `throttle`). User `/user` also requires `user.verified,abilities:user:access`. Controller, FormRequest, Resource, and response details are in the code; the full audit report accompanying this baseline records their mapping.

## Public

| Method | Path | Name | Handler | Access |
| --- | --- | --- | --- | --- |
| POST | `/register` | `auth.register` | `AuthController@register` | P, throttle |
| POST | `/login` | `auth.login` | `AuthController@login` | P, throttle |
| POST | `/forgot-password` | `auth.password.forgot` | `User/PasswordController@forgot` | P, throttle |
| POST | `/reset-password` | `auth.password.reset` | `User/PasswordController@reset` | P, throttle |
| GET | `/email/verify/{id}/{hash}` | `auth.email.verify` | `User/EmailVerificationController@verify` | P, signed, throttle |
| GET | `/experts/{profile:slug}` | `experts.show` | `PublicApi/ExpertProfileController@show` | P, throttle |
| POST | `/expert/auth/register` | `expert.auth.register` | `Expert/Auth/AuthController@register` | P, throttle |
| POST | `/expert/auth/login` | `expert.auth.login` | `Expert/Auth/AuthController@login` | P, throttle |
| POST | `/expert/auth/forgot-password` | `expert.auth.password.forgot` | `Expert/Auth/PasswordController@forgot` | P, throttle |
| POST | `/expert/auth/reset-password` | `expert.auth.password.reset` | `Expert/Auth/PasswordController@reset` | P, throttle |
| GET | `/expert/auth/email/verify/{id}/{hash}` | `expert.auth.email.verify` | `Expert/Auth/EmailVerificationController@verify` | P, signed, throttle |
| POST | `/admin/auth/login` | `admin.auth.login` | `Admin/AuthController@login` | P, throttle |
| POST | `/admin/auth/invitations/accept` | `admin.auth.invitations.accept` | `Admin/AuthController@acceptInvitation` | P, throttle |

## User

| Method | Path | Name | Handler | Access |
| --- | --- | --- | --- | --- |
| POST | `/email/verification-notification` | `auth.email.send` | `User/EmailVerificationController@send` | U, throttle |
| POST | `/logout` | `auth.logout` | `AuthController@logout` | U |
| POST | `/logout-all` | `auth.logout-all` | `AuthController@logoutAll` | U |
| GET | `/user` | `auth.user` | `AuthController@user` | U, verified, user:access |

## Expert authentication

| Method | Path | Name | Handler | Access |
| --- | --- | --- | --- | --- |
| GET | `/expert/auth/me` | `expert.auth.me` | `Expert/Auth/AuthController@me` | E |
| POST | `/expert/auth/email/verification-notification` | `expert.auth.email.send` | `Expert/Auth/EmailVerificationController@send` | E, throttle |
| PUT | `/expert/auth/password` | `expert.auth.password.update` | `Expert/Auth/PasswordController@update` | E |
| POST | `/expert/auth/logout` | `expert.auth.logout` | `Expert/Auth/AuthController@logout` | E |
| POST | `/expert/auth/logout-all` | `expert.auth.logout-all` | `Expert/Auth/AuthController@logoutAll` | E |

## Expert profile and availability

| Method | Path | Name | Handler | Access |
| --- | --- | --- | --- | --- |
| GET | `/expert/profile` | `expert.profile.show` | `ProfessionalProfileController@show` | EV |
| PUT | `/expert/profile` | `expert.profile.update` | `ProfessionalProfileController@update` | EV, throttle |
| POST | `/expert/profile/avatar` | `expert.profile.avatar.store` | `ProfessionalProfileController@uploadAvatar` | EV, throttle |
| DELETE | `/expert/profile/avatar` | `expert.profile.avatar.destroy` | `ProfessionalProfileController@deleteAvatar` | EV, throttle |
| GET | `/expert/profile/preview` | `expert.profile.preview` | `ProfessionalProfileController@preview` | EV |
| POST | `/expert/profile/publish` | `expert.profile.publish` | `ProfessionalProfileController@publish` | EV, throttle |
| POST | `/expert/profile/unpublish` | `expert.profile.unpublish` | `ProfessionalProfileController@unpublish` | EV, throttle |
| GET | `/expert/verified-scopes` | `expert.verified-scopes.index` | `ProfessionalProfileController@scopes` | EV |
| GET | `/expert/availability` | `expert.availability.show` | `AvailabilityController@show` | EV |
| PUT | `/expert/availability` | `expert.availability.update` | `AvailabilityController@update` | EV, throttle |

## Expert KYC

| Method | Path | Name | Handler | Access |
| --- | --- | --- | --- | --- |
| GET | `/expert/kyc` | `expert.kyc.show` | `Expert/KycController@show` | EV |
| PUT | `/expert/kyc` | `expert.kyc.update` | `Expert/KycController@update` | EV, throttle |
| POST | `/expert/kyc/documents` | `expert.kyc.documents.store` | `Expert/KycController@upload` | EV, throttle |
| DELETE | `/expert/kyc/documents/{document}` | `expert.kyc.documents.destroy` | `Expert/KycController@destroy` | EV, throttle |
| GET | `/expert/kyc/documents/{document}` | `expert.kyc.documents.show` | `Expert/KycController@download` | EV |
| POST | `/expert/kyc/submit` | `expert.kyc.submit` | `Expert/KycController@submit` | EV, throttle |

## Admin authentication and management

| Method | Path | Name | Handler | Access / Gate |
| --- | --- | --- | --- | --- |
| GET | `/admin/auth/me` | `admin.auth.me` | `Admin/AuthController@me` | A |
| POST | `/admin/auth/logout` | `admin.auth.logout` | `Admin/AuthController@logout` | A |
| POST | `/admin/auth/logout-all` | `admin.auth.logout-all` | `Admin/AuthController@logoutAll` | A |
| GET | `/admin/admins` | `admin.admins.index` | `AdminManagementController@index` | A, Admin.viewAny |
| POST | `/admin/admins` | `admin.admins.store` | `AdminManagementController@store` | A, Admin.create |
| GET | `/admin/admins/{admin}` | `admin.admins.show` | `AdminManagementController@show` | A, Admin.view |
| PUT/PATCH | `/admin/admins/{admin}` | `admin.admins.update` | `AdminManagementController@update` | A, Admin.update |
| PATCH | `/admin/admins/{admin}/status` | `admin.admins.status.update` | `AdminManagementController@updateStatus` | A, Admin.update |
| POST | `/admin/admins/{admin}/invitation` | `admin.admins.invitation.resend` | `AdminManagementController@resendInvitation` | A, Admin.update |
| GET | `/admin/roles` | `admin.roles.index` | `AuthorizationController@roles` | A, roles.viewAny |
| POST | `/admin/roles` | `admin.roles.store` | `AuthorizationController@storeRole` | A, Role.create |
| GET | `/admin/roles/{role}` | `admin.roles.show` | `AuthorizationController@showRole` | A, Role.view |
| PUT | `/admin/roles/{role}` | `admin.roles.update` | `AuthorizationController@updateRole` | A, Role.update |
| DELETE | `/admin/roles/{role}` | `admin.roles.destroy` | `AuthorizationController@destroyRole` | A, Role.delete |
| GET | `/admin/permissions` | `admin.permissions.index` | `AuthorizationController@permissions` | A, permissions.viewAny |
| POST | `/admin/admins/{admin}/roles/{role}` | `admin.admins.roles.assign` | `AuthorizationController@assignRole` | A, Admin.assignRoles |
| DELETE | `/admin/admins/{admin}/roles/{role}` | `admin.admins.roles.revoke` | `AuthorizationController@revokeRole` | A, Admin.assignRoles |
| GET | `/admin/users` | `admin.users.index` | `AdminUserController@index` | A, User.viewAny |
| GET | `/admin/users/{user}` | `admin.users.show` | `AdminUserController@show` | A, User.view |
| GET | `/admin/experts` | `admin.experts.index` | `AdminExpertController@index` | A, Expert.viewAny |
| GET | `/admin/experts/{expert}` | `admin.experts.show` | `AdminExpertController@show` | A, Expert.view |

## Admin KYC

| Method | Path | Name | Handler | Access |
| --- | --- | --- | --- | --- |
| GET | `/admin/kyc/applications` | `admin.kyc.applications.index` | `Admin/KycController@index` | AK |
| GET | `/admin/kyc/applications/{application}` | `admin.kyc.applications.show` | `Admin/KycController@show` | AK |
| POST | `/admin/kyc/applications/{application}/start-review` | `admin.kyc.applications.start-review` | `Admin/KycController@startReview` | AK, throttle |
| PUT | `/admin/kyc/applications/{application}/documents/{document}/review` | `admin.kyc.documents.review` | `Admin/KycController@reviewDocument` | AK, throttle |
| GET | `/admin/kyc/applications/{application}/documents/{document}` | `admin.kyc.documents.show` | `Admin/KycController@download` | AK |
| POST | `/admin/kyc/applications/{application}/approve` | `admin.kyc.applications.approve` | `Admin/KycController@approve` | AK, throttle |
| POST | `/admin/kyc/applications/{application}/reject` | `admin.kyc.applications.reject` | `Admin/KycController@reject` | AK, throttle |
| POST | `/admin/kyc/applications/{application}/request-information` | `admin.kyc.applications.request-information` | `Admin/KycController@requestInformation` | AK, throttle |

`GET /` is the Laravel welcome page; `GET /up` is the framework health route. No Case, Intake, or Matching endpoint exists in this baseline.
