<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_requests', function (Blueprint $table): void {
            $table->string('payment_external_reference')->nullable()->after('qr_code_url');
            $table->string('payment_provider_payment_id')->nullable()->after('payment_external_reference');
            $table->text('payment_ticket_url')->nullable()->after('payment_provider_payment_id');
            $table->string('payment_status', 60)->nullable()->after('payment_ticket_url');
            $table->string('payment_status_detail', 120)->nullable()->after('payment_status');
            $table->json('payment_payload')->nullable()->after('payment_status_detail');

            $table->index('payment_external_reference');
            $table->index(['payment_status', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('credit_requests', function (Blueprint $table): void {
            $table->dropIndex(['payment_external_reference']);
            $table->dropIndex(['payment_status', 'status']);
            $table->dropColumn([
                'payment_external_reference',
                'payment_provider_payment_id',
                'payment_ticket_url',
                'payment_status',
                'payment_status_detail',
                'payment_payload',
            ]);
        });
    }
};
