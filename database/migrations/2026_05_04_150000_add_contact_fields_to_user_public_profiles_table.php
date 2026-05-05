<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_public_profiles', function (Blueprint $table): void {
            $table->string('contact_whatsapp', 255)->nullable()->after('service_three_link_url');
            $table->string('contact_instagram', 255)->nullable()->after('contact_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('user_public_profiles', function (Blueprint $table): void {
            $table->dropColumn(['contact_whatsapp', 'contact_instagram']);
        });
    }
};
