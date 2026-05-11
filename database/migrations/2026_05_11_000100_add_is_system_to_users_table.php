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

        DB::table('users')
            ->leftJoin('tenant_student_trainee_links', 'tenant_student_trainee_links.student_user_id', '=', 'users.id')
            ->leftJoin('users as trainees', 'trainees.id', '=', 'tenant_student_trainee_links.trainee_user_id')
            ->where('users.profile_type', 'student')
            ->update([
                'users.is_add_credit' => DB::raw('CASE WHEN tenant_student_trainee_links.trainee_user_id IS NULL OR trainees.is_system = 1 THEN 1 ELSE 0 END'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_system']);
            $table->dropColumn('is_system');
        });
    }
};
