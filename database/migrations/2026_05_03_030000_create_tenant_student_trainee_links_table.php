<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_student_trainee_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainee_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_user_id']);
            $table->index(['tenant_id', 'trainee_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_student_trainee_links');
    }
};
