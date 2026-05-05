<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AI\AiLog;
use App\Models\Tenant\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
        }

        $logs = AiLog::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'tenant_id', 'user_id', 'type', 'prompt_hash', 'response_size', 'created_at']);

        return response()->json([
            'data' => $logs,
        ]);
    }
}
