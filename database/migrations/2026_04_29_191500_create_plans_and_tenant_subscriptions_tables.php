<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('max_students');
            $table->unsignedInteger('max_trainers');
            $table->unsignedInteger('ai_limit');
            $table->json('features')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->enum('status', ['active', 'canceled', 'overdue']);
            $table->unsignedInteger('ai_usage')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();

            $table->index(['tenant_id', 'status']);
            $table->index('plan_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql', 'sqlsrv'], true)) {
            DB::statement("CREATE UNIQUE INDEX tenant_subscriptions_one_active_per_tenant ON tenant_subscriptions (tenant_id) WHERE status = 'active'");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE tenant_subscriptions ADD active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) STORED");
            DB::statement('CREATE UNIQUE INDEX tenant_subscriptions_one_active_per_tenant ON tenant_subscriptions (tenant_id, active_marker)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('plans');
    }
};
