<?php

namespace App\Http\Controllers\Api\V1\Credits;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Credits\CreditRequestService;
use App\Transformers\Credits\CreditRequestTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CreditRequestController extends Controller
{
    public function __construct(
        private readonly CreditRequestService $creditRequestService,
        private readonly CreditRequestTransformer $creditRequestTransformer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$user] = $this->resolveAuthorizedRequester($request);

        $perPage = max(1, min((int) $request->integer('per_page', 10), 100));
        $requests = $this->creditRequestService->queryForRequester($user)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($this->creditRequestTransformer->transformPaginator($requests));
    }

    public function store(Request $request): JsonResponse
    {
        [$user, $tenant] = $this->resolveAuthorizedRequester($request);

        $payload = $request->validate([
            'credits_requested' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $creditRequest = $this->creditRequestService->createForRequester(
                $user,
                $tenant,
                (int) $payload['credits_requested'],
                isset($payload['note']) ? trim((string) $payload['note']) : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Solicitacao de credito criada com sucesso. Utilize o QR Code Pix para pagamento.',
            'data' => $this->creditRequestTransformer->transform($creditRequest),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        [$user] = $this->resolveAuthorizedRequester($request);
        $creditRequest = $this->creditRequestService->findOwnedRequestOrFail($user, $id);

        return response()->json([
            'data' => $this->creditRequestTransformer->transform($creditRequest),
        ]);
    }

    private function resolveAuthorizedRequester(Request $request): array
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isTrainee()) {
            return [$user, $tenant instanceof Tenant ? $tenant : null];
        }

        if ($user->profileType() === Role::STUDENT) {
            return [$user, $tenant instanceof Tenant ? $tenant : null];
        }

        if ($tenant instanceof Tenant && $user->getRole($tenant) === Role::ADMIN) {
            return [$user, $tenant];
        }

        abort(403, 'Apenas trainee, student ou admin podem solicitar creditos.');
    }
}
