<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('source_exercise_id');
            $table->string('target_exercise_id');
            $table->string('relationship_type');
            $table->decimal('score', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_exercise_id', 'relationship_type']);
            $table->index(['target_exercise_id', 'relationship_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_relationships');
    }
};
