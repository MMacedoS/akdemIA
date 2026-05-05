<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_landing_pages', function (Blueprint $table): void {
            $table->string('instagram_username', 100)->nullable()->after('cta_url');
            $table->text('instagram_access_token')->nullable()->after('instagram_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_landing_pages', function (Blueprint $table): void {
            $table->dropColumn(['instagram_username', 'instagram_access_token']);
        });
    }
};
