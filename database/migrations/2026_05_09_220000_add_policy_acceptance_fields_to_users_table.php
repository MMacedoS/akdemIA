<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('terms_version', 30)->nullable()->after('profile_type');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');
            $table->string('privacy_policy_version', 30)->nullable()->after('terms_accepted_at');
            $table->timestamp('privacy_policy_accepted_at')->nullable()->after('privacy_policy_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'terms_version',
                'terms_accepted_at',
                'privacy_policy_version',
                'privacy_policy_accepted_at',
            ]);
        });
    }
};
