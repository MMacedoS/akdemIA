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
            $table->boolean('is_system')->default(false)->after('is_add_credit');
            $table->index('is_system');
        });

        DB::table('users')
            ->whereRaw('LOWER(email) = ?', ['plataforma@academai.com.br'])
            ->update(['is_system' => true]);

        $studentIdsWithSystemTrainer = DB::table('tenant_student_trainee_links')
            ->join('users as trainees', 'trainees.id', '=', 'tenant_student_trainee_links.trainee_user_id')
            ->join('users as students', 'students.id', '=', 'tenant_student_trainee_links.student_user_id')
            ->where('students.profile_type', 'student')
            ->where('trainees.is_system', true)
            ->distinct()
            ->pluck('students.id');

        $studentIdsWithoutTrainer = DB::table('users')
            ->leftJoin('tenant_student_trainee_links', 'tenant_student_trainee_links.student_user_id', '=', 'users.id')
            ->where('users.profile_type', 'student')
            ->whereNull('tenant_student_trainee_links.trainee_user_id')
            ->pluck('users.id');

        DB::table('users')
            ->where('profile_type', 'student')
            ->update(['is_add_credit' => false]);

        $studentIdsWithAddCredit = $studentIdsWithSystemTrainer
            ->merge($studentIdsWithoutTrainer)
            ->unique()
            ->values();

        if ($studentIdsWithAddCredit->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $studentIdsWithAddCredit)
                ->update(['is_add_credit' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_system']);
            $table->dropColumn('is_system');
        });
    }
};
