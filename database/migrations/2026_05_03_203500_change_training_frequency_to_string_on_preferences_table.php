<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preferences', function (Blueprint $table) {
            $table->string('training_frequency')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('preferences', function (Blueprint $table) {
            $table->unsignedSmallInteger('training_frequency')->nullable()->change();
        });
    }
};
