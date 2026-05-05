<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SendEmailCommunicationRequest;
use App\Models\Tenant\Tenant;
use App\Notifications\UserCommunicationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class EmailCommunicationController extends Controller
{
    public function store(SendEmailCommunicationRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
        }

        $validated = $request->validated();

        $usersQuery = $tenant->users()
            ->select('users.*');

        if ($validated['target_type'] === 'role') {
            $usersQuery->wherePivot('role', (string) $validated['role']);
        }

        if ($validated['target_type'] === 'users') {
            $usersQuery->whereIn('users.id', (array) $validated['user_ids']);
        }

        $users = $usersQuery
            ->distinct()
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No users found for the provided criteria.',
            ], 404);
        }

        Notification::send(
            $users,
            new UserCommunicationNotification(
                subject: (string) $validated['subject'],
                message: (string) $validated['message'],
            )
        );

        return response()->json([
            'message' => 'Comunicacao enviada com sucesso.',
            'total_sent' => $users->count(),
        ]);
    }
}
