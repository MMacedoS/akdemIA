<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exercise_media_caches') || Schema::hasColumn('exercise_media_caches', 'localized_name_pt_br')) {
            return;
        }

        Schema::table('exercise_media_caches', function (Blueprint $table) {
            $table->string('localized_name_pt_br')->nullable()->after('remote_exercise_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exercise_media_caches') || ! Schema::hasColumn('exercise_media_caches', 'localized_name_pt_br')) {
            return;
        }

        Schema::table('exercise_media_caches', function (Blueprint $table) {
            $table->dropColumn('localized_name_pt_br');
        });
    }
};
