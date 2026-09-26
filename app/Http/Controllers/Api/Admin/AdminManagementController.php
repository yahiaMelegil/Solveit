<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminManagementController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Admin::class);

        $admins = Admin::query()
            ->with('roles')
            ->latest('id')
            ->paginate(25);

        return AdminResource::collection($admins);
    }

    public function show(Admin $admin): AdminResource
    {
        Gate::authorize('view', $admin);

        return new AdminResource($admin->load('roles.permissions', 'permissions'));
    }
}
