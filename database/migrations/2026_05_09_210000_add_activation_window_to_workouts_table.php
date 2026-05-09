<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table): void {
            $table->timestamp('activated_at')->nullable()->after('regeneration_request');
            $table->timestamp('active_until_at')->nullable()->after('activated_at');
            $table->index(['request_status', 'active_until_at']);
        });

        DB::table('workouts')
            ->select(['id', 'created_at'])
            ->where('request_status', 'active')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $workout): void {
                $activatedAt = $workout->created_at !== null
                    ? CarbonImmutable::parse((string) $workout->created_at)
                    : CarbonImmutable::now();

                DB::table('workouts')
                    ->where('id', $workout->id)
                    ->update([
                        'activated_at' => $activatedAt,
                        'active_until_at' => $activatedAt->addDays(60),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table): void {
            $table->dropIndex(['request_status', 'active_until_at']);
            $table->dropColumn(['activated_at', 'active_until_at']);
        });
    }
};
