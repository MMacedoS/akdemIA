<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_landing_settings', function (Blueprint $table): void {
            $table->string('hero_image_url', 2000)->nullable()->after('hero_description');
            $table->string('about_title', 160)->nullable()->after('secondary_cta_url');
            $table->text('about_content')->nullable()->after('about_title');
            $table->string('differentials_section_title', 120)->nullable()->after('professionals_section_title');
            $table->string('contact_section_title', 120)->nullable()->after('differentials_section_title');
            $table->text('contact_description')->nullable()->after('contact_section_title');
            $table->string('contact_email', 190)->nullable()->after('contact_description');
            $table->string('contact_whatsapp', 40)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('system_landing_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'hero_image_url',
                'about_title',
                'about_content',
                'differentials_section_title',
                'contact_section_title',
                'contact_description',
                'contact_email',
                'contact_whatsapp',
            ]);
        });
    }
};
