<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_data', function (Blueprint $table) {
            $table->string('activity_level', 50)->nullable()->change();
            $table->decimal('imc', 6, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('physical_data', function (Blueprint $table) {
            $table->string('activity_level', 50)->nullable(false)->change();
            $table->decimal('imc', 6, 2)->nullable(false)->change();
        });
    }
};
