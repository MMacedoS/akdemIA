<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'stripe_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('stripe_id')->nullable()->unique()->after('slug');
            });
        }

        if (Schema::hasTable('tenant_subscriptions') && ! Schema::hasColumn('tenant_subscriptions', 'stripe_subscription_id')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->string('stripe_subscription_id')->nullable()->unique()->after('plan_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_subscriptions') && Schema::hasColumn('tenant_subscriptions', 'stripe_subscription_id')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->dropUnique(['stripe_subscription_id']);
                $table->dropColumn('stripe_subscription_id');
            });
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'stripe_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropUnique(['stripe_id']);
                $table->dropColumn('stripe_id');
            });
        }
    }
};
