<?php

namespace App\Services\Workouts;

use App\Models\Credits\CreditTransaction;
use App\Models\Tenant\Tenant;
use Carbon\CarbonImmutable;
use RuntimeException;

class WorkoutGenerationCooldownService
{
    private const COOLDOWN_DAYS = 7;

    public function withCreditChargeMetadata(array $safetyFlags, CreditTransaction $creditTransaction): array
    {
        $safetyFlags['credit_charge'] = [
            'transaction_id' => (int) $creditTransaction->id,
            'amount' => abs((int) $creditTransaction->amount),
            'type' => (string) $creditTransaction->type,
        ];

        return $safetyFlags;
    }

    public function assertGenerationAllowed(?Tenant $tenant, int $studentUserId, string $subjectLabel): void
    {
        $blockedUntil = $this->resolveActiveBlockedUntil($tenant, $studentUserId);

        if (! $blockedUntil instanceof CarbonImmutable) {
            return;
        }

        throw new RuntimeException(
            'A geracao de treino para ' . $subjectLabel
                . ' esta temporariamente bloqueada ate ' . $blockedUntil->format('d/m/Y H:i')
                . ' por causa de uma falha na tentativa anterior. Aguarde pelo menos 7 dias para tentar novamente.'
        );
    }

    public function cooldownMetadata(?Tenant $tenant, int $studentUserId, string $errorMessage): array
    {
        return [
            'generation_block' => [
                'student_user_id' => $studentUserId,
                'tenant_id' => $tenant?->id,
                'reason' => $errorMessage,
                'blocked_until' => CarbonImmutable::now()->addDays(self::COOLDOWN_DAYS)->toIso8601String(),
            ],
        ];
    }

    public function localizeFailureMessage(string $message): string
    {
        $trimmed = trim($message);

        return match (true) {
            $trimmed === 'Weekly plan exceeds hinge frequency recovery threshold.'
            => 'O plano semanal excedeu o limite de recuperacao para exercicios de hinge.',
            str_starts_with($trimmed, 'Session exceeds hinge fatigue threshold for day: ')
            => 'A sessao excedeu o limite de fadiga para exercicios de hinge no dia: ' . substr($trimmed, strlen('Session exceeds hinge fatigue threshold for day: ')),
            str_starts_with($trimmed, 'Session exceeds heavy compound fatigue threshold for day: ')
            => 'A sessao excedeu o limite de fadiga para exercicios compostos pesados no dia: ' . substr($trimmed, strlen('Session exceeds heavy compound fatigue threshold for day: ')),
            default => $trimmed,
        };
    }

    private function resolveActiveBlockedUntil(?Tenant $tenant, int $studentUserId): ?CarbonImmutable
    {
        $transaction = CreditTransaction::query()
            ->where('type', 'refund_workout_error')
            ->where('metadata->generation_block->student_user_id', $studentUserId)
            ->when(
                $tenant instanceof Tenant,
                fn($query) => $query->where('tenant_id', $tenant->id),
                fn($query) => $query->whereNull('tenant_id'),
            )
            ->orderByDesc('created_at')
            ->first();

        $blockedUntil = data_get($transaction?->metadata, 'generation_block.blocked_until');

        if (! is_string($blockedUntil) || trim($blockedUntil) === '') {
            return null;
        }

        $parsed = CarbonImmutable::parse($blockedUntil);

        return $parsed->isPast() ? null : $parsed;
    }
}
