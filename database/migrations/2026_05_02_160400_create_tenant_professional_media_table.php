<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_professional_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video']);
            $table->string('media_url');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'professional_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_professional_media');
    }
};
