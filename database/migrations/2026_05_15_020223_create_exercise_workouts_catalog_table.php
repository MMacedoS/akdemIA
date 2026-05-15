<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exercise_workouts_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workouts_catalog_id')->constrained('workouts_catalogs')->cascadeOnDelete();
            $table->foreignId('exercise_media_cache_id')->constrained('exercise_media_caches')->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['workouts_catalog_id', 'exercise_media_cache_id'], 'workouts_catalog_exercise_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_workouts_catalogs');
    }
};
