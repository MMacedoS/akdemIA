<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_landing_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->string('hero_image_url')->nullable();
            $table->string('hero_video_url')->nullable();
            $table->string('primary_color', 20)->default('#0f6eaf');
            $table->string('secondary_color', 20)->default('#13a9bf');
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_landing_pages');
    }
};
