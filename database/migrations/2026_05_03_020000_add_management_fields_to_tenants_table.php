<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('contact_email', 190)->nullable()->after('slug');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->string('document_number', 40)->nullable()->after('contact_phone');
            $table->text('notes')->nullable()->after('stripe_id');

            $table->index('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['contact_email']);
            $table->dropColumn(['contact_email', 'contact_phone', 'document_number', 'notes']);
        });
    }
};
