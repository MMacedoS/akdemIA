<?php

namespace App\Services\Credits;

use App\Models\Credits\CreditRequest;
use App\Models\Credits\CreditTransaction;
use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreditService
{
    public function consumeCredits(User $user, int $amount, string $type, array $metadata = [], ?Tenant $tenant = null): void
    {
        if ($amount <= 0) {
            throw new RuntimeException('Invalid credit amount for consumption.');
        }

        DB::transaction(function () use ($user, $amount, $type, $metadata, $tenant): void {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);

            if ($lockedUser === null) {
                throw new RuntimeException('User not found for credit operation.');
            }

            $currentBalance = (int) $lockedUser->credits_balance;

            if ($currentBalance < $amount) {
                throw new RuntimeException('Saldo de credito insuficiente para a operacao solicitada.');
            }

            $lockedUser->credits_balance = $currentBalance - $amount;
            $lockedUser->save();

            CreditTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'actor_user_id' => null,
                'tenant_id' => $tenant?->id,
                'amount' => -$amount,
                'type' => $type,
                'description' => 'Consumo automatico de creditos.',
                'metadata' => $metadata,
            ]);
        }, 3);
    }

    public function addCredits(
        User $targetUser,
        int $amount,
        User $actor,
        string $type,
        string $description,
        array $metadata = [],
        ?Tenant $tenant = null,
        ?CreditRequest $creditRequest = null,
    ): void {
        if ($amount <= 0) {
            throw new RuntimeException('Invalid credit amount for grant.');
        }

        DB::transaction(function () use ($targetUser, $amount, $actor, $type, $description, $metadata, $tenant, $creditRequest): void {
            $lockedUser = User::query()->lockForUpdate()->find($targetUser->id);

            if ($lockedUser === null) {
                throw new RuntimeException('User not found for credit operation.');
            }

            $lockedUser->credits_balance = (int) $lockedUser->credits_balance + $amount;
            $lockedUser->save();

            CreditTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'actor_user_id' => $actor->id,
                'tenant_id' => $tenant?->id,
                'credit_request_id' => $creditRequest?->id,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        }, 3);
    }
}
