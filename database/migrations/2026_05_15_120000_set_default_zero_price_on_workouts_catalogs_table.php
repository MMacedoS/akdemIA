<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workouts_catalogs')) {
            return;
        }

        DB::statement('ALTER TABLE workouts_catalogs MODIFY price INT NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (! Schema::hasTable('workouts_catalogs')) {
            return;
        }

        DB::statement('ALTER TABLE workouts_catalogs MODIFY price INT NOT NULL DEFAULT 1');
    }
};
