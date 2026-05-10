<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAssignedTraineeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_assigned_trainer_by_context(): void
    {
        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        $standaloneTrainee = User::factory()->create([
            'email' => 'standalone-trainer@test.local',
            'profile_type' => Role::TRAINER->value,
        ]);

        $tenantTrainee = User::factory()->create([
            'email' => 'tenant-trainer@test.local',
            'profile_type' => Role::TRAINER->value,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant Teste',
            'slug' => 'tenant-teste',
            'contact_email' => 'tenant@test.local',
            'is_active' => true,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $standaloneTrainee->id,
            'linked_by_user_id' => $standaloneTrainee->id,
            'note' => null,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $student->id,
            'trainee_user_id' => $tenantTrainee->id,
            'linked_by_user_id' => $tenantTrainee->id,
            'note' => null,
        ]);

        $this->assertSame($standaloneTrainee->id, $student->assignedTrainee()?->id);
        $this->assertSame($tenantTrainee->id, $student->assignedTrainee($tenant)?->id);
    }
}
