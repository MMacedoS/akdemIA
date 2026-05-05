<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_media_caches', function (Blueprint $table) {
            $table->id();
            $table->string('workoutx_name')->unique();
            $table->string('query_name')->nullable();
            $table->text('remote_gif_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_media_caches');
    }
};
