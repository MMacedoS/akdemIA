<?php

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TenantAuthService
{
    public function createSelectionToken(User $user): string
    {
        $selectionToken = Str::random(64);

        Cache::put(
            $this->selectionTokenCacheKey($selectionToken),
            $user->id,
            Carbon::now()->addMinutes(10),
        );

        return $selectionToken;
    }

    public function consumeSelectionToken(string $selectionToken): ?int
    {
        $cacheKey = $this->selectionTokenCacheKey($selectionToken);
        $userId = Cache::get($cacheKey);

        if (! is_int($userId)) {
            return null;
        }

        Cache::forget($cacheKey);

        return $userId;
    }

    public function generateTenantToken(User $user, Tenant $tenant): string
    {
        $role = $user->getRole($tenant);

        $payload = [
            'sub' => $user->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => $role?->value,
            'iat' => Carbon::now()->timestamp,
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            $jsonPayload = '{}';
        }

        $basePayload = base64_encode($jsonPayload);
        $signature = hash_hmac('sha256', $basePayload, (string) config('app.key'));

        return $basePayload . '.' . $signature;
    }

    private function selectionTokenCacheKey(string $selectionToken): string
    {
        return 'tenant_selection:' . $selectionToken;
    }
}
