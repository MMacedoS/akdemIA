<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 80);
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['domain', 'key']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
