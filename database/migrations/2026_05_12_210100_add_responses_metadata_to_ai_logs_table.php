<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->string('operation', 60)->nullable()->after('type');
            $table->string('provider', 30)->nullable()->after('operation');
            $table->string('model', 120)->nullable()->after('provider');
            $table->string('request_hash', 64)->nullable()->after('prompt_hash');
            $table->string('cache_key')->nullable()->after('response_size');
            $table->boolean('cache_hit')->default(false)->after('cache_key');
            $table->string('retrieval_mode', 40)->nullable()->after('cache_hit');
            $table->string('vector_store_id', 120)->nullable()->after('retrieval_mode');
            $table->string('file_id', 120)->nullable()->after('vector_store_id');
            $table->unsignedSmallInteger('http_status')->nullable()->after('file_id');
            $table->unsignedInteger('latency_ms')->nullable()->after('http_status');
            $table->unsignedInteger('prompt_tokens')->nullable()->after('latency_ms');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('completion_tokens');
            $table->json('metadata')->nullable()->after('total_tokens');

            $table->index(['provider', 'created_at']);
            $table->index(['operation', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->dropIndex(['provider', 'created_at']);
            $table->dropIndex(['operation', 'created_at']);
            $table->dropColumn([
                'operation',
                'provider',
                'model',
                'request_hash',
                'cache_key',
                'cache_hit',
                'retrieval_mode',
                'vector_store_id',
                'file_id',
                'http_status',
                'latency_ms',
                'prompt_tokens',
                'completion_tokens',
                'total_tokens',
                'metadata',
            ]);
        });
    }
};
