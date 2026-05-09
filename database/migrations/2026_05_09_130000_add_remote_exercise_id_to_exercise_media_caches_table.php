<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_media_caches', function (Blueprint $table) {
            $table->string('remote_exercise_id', 64)->nullable()->after('id');
            $table->unique('remote_exercise_id');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_media_caches', function (Blueprint $table) {
            $table->dropUnique('exercise_media_caches_remote_exercise_id_unique');
            $table->dropColumn('remote_exercise_id');
        });
    }
};
