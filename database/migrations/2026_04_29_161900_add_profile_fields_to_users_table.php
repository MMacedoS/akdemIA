<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('name');
            $table->string('gender', 30)->nullable()->after('birth_date');
            $table->decimal('height', 5, 2)->nullable()->after('gender');
            $table->decimal('weight', 6, 2)->nullable()->after('height');
            $table->string('goal', 500)->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender', 'height', 'weight', 'goal']);
        });
    }
};
