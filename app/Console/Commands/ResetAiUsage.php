<?php

namespace App\Console\Commands;

use App\Services\AI\AiUsageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:usage-reset')]
#[Description('Reset monthly AI usage for active tenant subscriptions.')]
class ResetAiUsage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AiUsageService $aiUsageService): int
    {
        $updated = $aiUsageService->resetMonthlyUsage();

        $this->info("AI usage reset finished. Updated subscriptions: {$updated}.");

        return self::SUCCESS;
    }
}
