<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_public_profiles', function (Blueprint $table): void {
            $table->string('theme_preset', 40)->default('myhra_bordeaux')->after('hero_video_url');
        });

        Schema::table('tenant_landing_pages', function (Blueprint $table): void {
            $table->string('theme_preset', 40)->default('myhra_bordeaux')->after('hero_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_public_profiles', function (Blueprint $table): void {
            $table->dropColumn('theme_preset');
        });

        Schema::table('tenant_landing_pages', function (Blueprint $table): void {
            $table->dropColumn('theme_preset');
        });
    }
};
