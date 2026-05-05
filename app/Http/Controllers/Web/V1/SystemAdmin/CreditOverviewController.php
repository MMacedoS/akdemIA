<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditRequest;
use App\Models\Credits\CreditTransaction;
use App\Models\User;
use Illuminate\View\View;

class CreditOverviewController extends Controller
{
    public function index(): View
    {
        $pendingRequests = CreditRequest::query()
            ->with(['requester', 'targetUser', 'tenant'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(15)
            ->get();

        $recentTransactions = CreditTransaction::query()
            ->with(['user', 'actor'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $users = User::query()
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name', 'email', 'credits_balance']);

        $summary = [
            'pending_requests' => CreditRequest::query()->where('status', 'pending')->count(),
            'approved_requests' => CreditRequest::query()->where('status', 'approved')->count(),
            'total_credit_granted' => (int) CreditTransaction::query()->where('amount', '>', 0)->sum('amount'),
            'total_credit_consumed' => abs((int) CreditTransaction::query()->where('amount', '<', 0)->sum('amount')),
        ];

        return view('web.v1.system_admin.credits.index', [
            'pendingRequests' => $pendingRequests,
            'recentTransactions' => $recentTransactions,
            'users' => $users,
            'summary' => $summary,
        ]);
    }
}
