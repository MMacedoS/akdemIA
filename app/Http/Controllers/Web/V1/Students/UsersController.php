<?php

namespace App\Http\Controllers\Web\V1\Students;

use App\Enums\Role;
use App\Http\Controllers\Web\V1\PanelUsersController;

class UsersController extends PanelUsersController
{
    protected function role(): Role
    {
        return Role::STUDENT;
    }

    protected function viewBase(): string
    {
        return 'web.v1.students';
    }

    protected function routePrefix(): string
    {
        return 'students';
    }
}
