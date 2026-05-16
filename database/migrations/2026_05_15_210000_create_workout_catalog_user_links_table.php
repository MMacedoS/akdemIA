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
        Schema::create('workout_catalog_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workouts_catalog_id')->constrained('workouts_catalogs')->cascadeOnDelete();
            $table->unsignedInteger('credits_consumed')->default(0);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'workouts_catalog_id'], 'workout_catalog_user_links_unique');
            $table->index('workouts_catalog_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_catalog_user_links');
    }
};
