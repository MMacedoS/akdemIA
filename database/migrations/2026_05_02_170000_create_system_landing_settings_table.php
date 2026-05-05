<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_landing_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('hero_title', 255)->nullable();
            $table->text('hero_description')->nullable();
            $table->string('primary_cta_text', 80)->nullable();
            $table->string('primary_cta_url', 2000)->nullable();
            $table->string('secondary_cta_text', 80)->nullable();
            $table->string('secondary_cta_url', 2000)->nullable();
            $table->string('tenants_section_title', 120)->nullable();
            $table->string('professionals_section_title', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_landing_settings');
    }
};
