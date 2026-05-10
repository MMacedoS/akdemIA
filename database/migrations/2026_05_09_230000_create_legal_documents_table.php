<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50);
            $table->string('title', 150);
            $table->string('slug', 150)->unique();
            $table->string('version', 50);
            $table->date('effective_date')->nullable();
            $table->longText('content_html');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('type');
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
