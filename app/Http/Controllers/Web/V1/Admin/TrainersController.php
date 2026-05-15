<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Web\V1\PanelUsersController;

class TrainersController extends PanelUsersController
{
    protected function role(): Role
    {
        return Role::TRAINER;
    }

    protected function viewBase(): string
    {
        return 'web.v1.admin.trainers';
    }

    protected function routePrefix(): string
    {
        return 'admin.trainers';
    }
}
