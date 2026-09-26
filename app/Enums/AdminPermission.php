<?php

namespace App\Enums;

enum AdminPermission: string
{
    case AdminsViewAny = 'admins.viewAny';
    case AdminsView = 'admins.view';
    case AdminsCreate = 'admins.create';
    case AdminsUpdate = 'admins.update';
    case AdminsAssignRoles = 'admins.assignRoles';

    case RolesViewAny = 'roles.viewAny';
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case PermissionsViewAny = 'permissions.viewAny';

    case UsersViewAny = 'users.viewAny';
    case UsersView = 'users.view';
    case UsersUpdate = 'users.update';

    case ExpertsViewAny = 'experts.viewAny';
    case ExpertsView = 'experts.view';
    case ExpertsUpdate = 'experts.update';
    case ExpertsReviewKyc = 'experts.reviewKyc';

    case SupportViewAny = 'support.viewAny';
    case SupportView = 'support.view';
    case SupportUpdate = 'support.update';
}
