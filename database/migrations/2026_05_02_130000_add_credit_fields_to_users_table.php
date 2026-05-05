<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('credits_balance')->default(0)->after('avatar_path');
            $table->boolean('is_system_admin')->default(false)->after('credits_balance');
            $table->index('is_system_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_system_admin']);
            $table->dropColumn(['credits_balance', 'is_system_admin']);
        });
    }
};
