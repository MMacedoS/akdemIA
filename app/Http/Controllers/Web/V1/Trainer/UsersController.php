<?php

namespace App\Http\Controllers\Web\V1\Trainer;

use App\Enums\Role;
use App\Http\Controllers\Web\V1\PanelUsersController;

class UsersController extends PanelUsersController
{
    protected function role(): Role
    {
        return Role::TRAINER;
    }

    protected function viewBase(): string
    {
        return 'web.v1.trainer';
    }

    protected function routePrefix(): string
    {
        return 'trainer';
    }
}
