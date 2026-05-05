<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->string('request_status', 20)->default('active')->after('status');
            $table->text('regeneration_request')->nullable()->after('request_status');
            $table->index(['tenant_id', 'user_id', 'request_status']);
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id', 'request_status']);
            $table->dropColumn(['request_status', 'regeneration_request']);
        });
    }
};
