<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exercise_media_caches')) {
            Schema::create('exercise_media_caches', function (Blueprint $table) {
                $table->id();
                $table->string('remote_exercise_id', 64)->nullable()->unique();
                $table->string('workoutx_name')->unique();
                $table->string('query_name')->nullable();
                $table->text('remote_gif_url')->nullable();
                $table->string('storage_path')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (Schema::hasColumn('exercise_media_caches', 'remote_exercise_id')) {
            return;
        }

        Schema::table('exercise_media_caches', function (Blueprint $table) {
            $table->string('remote_exercise_id', 64)->nullable()->after('id');
            $table->unique('remote_exercise_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exercise_media_caches') || ! Schema::hasColumn('exercise_media_caches', 'remote_exercise_id')) {
            return;
        }

        Schema::table('exercise_media_caches', function (Blueprint $table) {
            $table->dropUnique('exercise_media_caches_remote_exercise_id_unique');
            $table->dropColumn('remote_exercise_id');
        });
    }
};
