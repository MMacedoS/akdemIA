<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Web\V1\PanelUsersController;

class UsersController extends PanelUsersController
{
    protected function role(): Role
    {
        return Role::ADMIN;
    }

    protected function viewBase(): string
    {
        return 'web.v1.admin.users';
    }

    protected function routePrefix(): string
    {
        return 'admin.users';
    }
}
