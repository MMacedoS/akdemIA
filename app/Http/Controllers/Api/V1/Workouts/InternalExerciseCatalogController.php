<?php

namespace App\Http\Controllers\Api\V1\Workouts;

use App\Http\Controllers\Controller;
use App\Services\Workouts\ExerciseCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalExerciseCatalogController extends Controller
{
    public function __construct(
        private readonly ExerciseCatalogService $exerciseCatalogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $expectedKey = trim((string) config('services.internal_catalog.api_key', ''));
        $providedKey = trim((string) $request->header('X-Internal-Catalog-Key', ''));

        if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        $result = $this->exerciseCatalogService->listForInternalApi(
            $request->query('focus'),
            $request->query('search'),
            $request->query('translation_status'),
            (int) $request->query('limit', 50),
            (int) $request->query('offset', 0),
        );

        return response()->json($result);
    }
}
