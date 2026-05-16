<?php

namespace App\Services\Workouts;

use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\WorkoutCatalog;
use App\Models\Workout\WorkoutCatalogUserLink;
use App\Services\Credits\CreditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WorkoutCatalogLinkService
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function link(User $user, WorkoutCatalog $catalog, ?Tenant $tenant = null): array
    {
        try {
            return DB::transaction(function () use ($user, $catalog, $tenant): array {
                $existingLink = WorkoutCatalogUserLink::query()
                    ->where('user_id', $user->id)
                    ->where('workouts_catalog_id', $catalog->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingLink instanceof WorkoutCatalogUserLink) {
                    return [
                        'already_linked' => true,
                        'link' => $existingLink,
                        'credits_consumed' => 0,
                    ];
                }

                $creditsToConsume = max(0, (int) $catalog->price);

                if ($creditsToConsume > 0) {
                    $this->creditService->consumeCredits(
                        $user,
                        $creditsToConsume,
                        'consume_catalog_link',
                        [
                            'context' => 'api_v1_catalog_link',
                            'catalog_id' => (int) $catalog->id,
                            'catalog_name' => (string) $catalog->name,
                            'tenant_id' => $tenant?->id,
                            'student_id' => (int) $user->id,
                        ],
                        $tenant,
                    );
                }

                $link = WorkoutCatalogUserLink::query()->create([
                    'user_id' => (int) $user->id,
                    'workouts_catalog_id' => (int) $catalog->id,
                    'credits_consumed' => $creditsToConsume,
                    'linked_at' => now(),
                ]);

                return [
                    'already_linked' => false,
                    'link' => $link,
                    'credits_consumed' => $creditsToConsume,
                ];
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateLinkException($exception)) {
                throw $exception;
            }

            $existingLink = WorkoutCatalogUserLink::query()
                ->where('user_id', $user->id)
                ->where('workouts_catalog_id', $catalog->id)
                ->firstOrFail();

            return [
                'already_linked' => true,
                'link' => $existingLink,
                'credits_consumed' => 0,
            ];
        }
    }

    private function isDuplicateLinkException(QueryException $exception): bool
    {
        if ($exception->getCode() === '23000') {
            return true;
        }

        return str_contains(mb_strtolower($exception->getMessage()), 'duplicate');
    }
}
