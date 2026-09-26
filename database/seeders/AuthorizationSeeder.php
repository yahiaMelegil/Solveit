<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthorizationSeeder extends Seeder
{
    private const GUARD = 'admin';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, self::GUARD);
        }

        $roles = collect(AdminRole::cases())->mapWithKeys(
            fn (AdminRole $role): array => [
                $role->value => Role::findOrCreate($role->value, self::GUARD),
            ],
        );

        $roles[AdminRole::SuperAdmin->value]->syncPermissions(
            Permission::query()->where('guard_name', self::GUARD)->get(),
        );

        $roles[AdminRole::UserManager->value]->syncPermissions([
            AdminPermission::AdminsViewAny->value,
            AdminPermission::AdminsView->value,
            AdminPermission::UsersViewAny->value,
            AdminPermission::UsersView->value,
            AdminPermission::UsersUpdate->value,
            AdminPermission::ExpertsViewAny->value,
            AdminPermission::ExpertsView->value,
        ]);

        $roles[AdminRole::SupportAdmin->value]->syncPermissions([
            AdminPermission::UsersViewAny->value,
            AdminPermission::UsersView->value,
            AdminPermission::ExpertsViewAny->value,
            AdminPermission::ExpertsView->value,
            AdminPermission::SupportViewAny->value,
            AdminPermission::SupportView->value,
            AdminPermission::SupportUpdate->value,
        ]);

        $roles[AdminRole::KycReviewer->value]->syncPermissions([
            AdminPermission::ExpertsViewAny->value,
            AdminPermission::ExpertsView->value,
            AdminPermission::ExpertsReviewKyc->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
