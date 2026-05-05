<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditRequest;
use App\Models\Credits\CreditTransaction;
use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $summary = [
            'total_users' => User::query()->count(),
            'total_tenants' => Tenant::query()->count(),
            'total_trainees' => User::query()->where('profile_type', 'trainer')->count(),
            'pending_requests' => CreditRequest::query()->where('status', 'pending')->count(),
            'last_transaction_at' => CreditTransaction::query()->latest('id')->value('created_at'),
        ];

        return view('web.v1.system_admin.dashboard.index', [
            'summary' => $summary,
        ]);
    }
}
