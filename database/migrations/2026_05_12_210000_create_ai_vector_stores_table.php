<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_vector_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('catalog_type', 80);
            $table->string('vector_store_id', 120);
            $table->string('vector_store_name', 160)->nullable();
            $table->string('file_id', 120)->nullable();
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path');
            $table->string('source_hash', 64);
            $table->string('status', 40)->default('ready');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'catalog_type']);
            $table->index(['vector_store_id']);
            $table->index(['source_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_vector_stores');
    }
};