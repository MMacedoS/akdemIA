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
        Schema::create('workouts_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->text('description');
            $table->integer('quantity_exercises')->default(0);
            $table->integer('price')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('path_image', 100)->nullable();
            $table->boolean('is_public')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workouts_catalogs');
    }
};
