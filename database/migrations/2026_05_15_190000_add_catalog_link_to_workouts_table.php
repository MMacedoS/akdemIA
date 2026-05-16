<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table): void {
            $table->foreignId('source_workout_catalog_id')
                ->nullable()
                ->after('user_id')
                ->constrained('workouts_catalogs')
                ->nullOnDelete();

            $table->string('source_workout_catalog_name', 120)
                ->nullable()
                ->after('source_workout_catalog_id');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_workout_catalog_id');
            $table->dropColumn('source_workout_catalog_name');
        });
    }
};
