<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_public_profiles', function (Blueprint $table): void {
            $table->string('service_section_title')->nullable()->after('skills');
            $table->string('service_one_label', 80)->nullable()->after('service_section_title');
            $table->string('service_one_title')->nullable()->after('service_one_label');
            $table->text('service_one_description')->nullable()->after('service_one_title');
            $table->string('service_one_link_label', 80)->nullable()->after('service_one_description');
            $table->string('service_one_link_url')->nullable()->after('service_one_link_label');
            $table->string('service_two_label', 80)->nullable()->after('service_one_link_url');
            $table->string('service_two_title')->nullable()->after('service_two_label');
            $table->text('service_two_description')->nullable()->after('service_two_title');
            $table->string('service_two_link_label', 80)->nullable()->after('service_two_description');
            $table->string('service_two_link_url')->nullable()->after('service_two_link_label');
            $table->string('service_three_label', 80)->nullable()->after('service_two_link_url');
            $table->string('service_three_title')->nullable()->after('service_three_label');
            $table->text('service_three_description')->nullable()->after('service_three_title');
            $table->string('service_three_link_label', 80)->nullable()->after('service_three_description');
            $table->string('service_three_link_url')->nullable()->after('service_three_link_label');
        });
    }

    public function down(): void
    {
        Schema::table('user_public_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'service_section_title',
                'service_one_label',
                'service_one_title',
                'service_one_description',
                'service_one_link_label',
                'service_one_link_url',
                'service_two_label',
                'service_two_title',
                'service_two_description',
                'service_two_link_label',
                'service_two_link_url',
                'service_three_label',
                'service_three_title',
                'service_three_description',
                'service_three_link_label',
                'service_three_link_url',
            ]);
        });
    }
};
