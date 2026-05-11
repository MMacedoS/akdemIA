<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_add_credit')->default(true)->after('credits_balance');
        });

        $studentIdsWithoutPlatformTrainer = DB::table('tenant_student_trainee_links')
            ->join('users as trainees', 'trainees.id', '=', 'tenant_student_trainee_links.trainee_user_id')
            ->where('tenant_student_trainee_links.trainee_user_id', '!=', 4)
            ->whereRaw('LOWER(trainees.email) != ?', ['plataforma@academai.com.br'])
            ->distinct()
            ->pluck('tenant_student_trainee_links.student_user_id');

        if ($studentIdsWithoutPlatformTrainer->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $studentIdsWithoutPlatformTrainer)
                ->update(['is_add_credit' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_add_credit');
        });
    }
};
