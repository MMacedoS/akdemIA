<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('@akademia123');
        $adminPassword = Hash::make('@Akdemia1707');

        $tenantPlataforma = Tenant::query()->updateOrCreate(
            ['slug' => 'plataforma-academai'],
            ['name' => 'Plataforma AcademAI', 'is_active' => true],
        );

        $systemAdmin = User::query()->updateOrCreate(
            ['email' => 'administrador@academai.com.br'],
            [
                'name' => 'Administrador da Plataforma',
                'password' => $adminPassword,
                'profile_type' => Role::ADMIN->value,
                'is_system_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $tenantAdmin = User::query()->updateOrCreate(
            ['email' => 'admplataforma@academai.com.br'],
            [
                'name' => 'Admin Plataforma',
                'password' => $adminPassword,
                'profile_type' => Role::ADMIN->value,
                'is_system_admin' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $student = User::query()->updateOrCreate(
            ['email' => 'contato@academai.com.br'],
            [
                'name' => 'Contato Plataforma',
                'password' => $defaultPassword,
                'profile_type' => Role::STUDENT->value,
                'is_system_admin' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $trainer = User::query()->updateOrCreate(
            ['email' => 'plataforma@academai.com.br'],
            [
                'name' => 'Trainer Plataforma',
                'password' => $defaultPassword,
                'profile_type' => Role::TRAINER->value,
                'is_system_admin' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $systemAdmin->tenants()->detach();

        $tenantAdmin->tenants()->sync([
            $tenantPlataforma->id => ['role' => Role::ADMIN->value],
        ]);

        $trainer->tenants()->syncWithoutDetaching([
            $tenantPlataforma->id => ['role' => Role::TRAINER->value],
        ]);

        $student->tenants()->sync([
            $tenantPlataforma->id => ['role' => Role::STUDENT->value],
        ]);

        DB::table('tenant_trainee')->updateOrInsert(
            [
                'tenant_id' => $tenantPlataforma->id,
                'trainee_user_id' => $trainer->id,
            ],
            [
                'linked_by_user_id' => $tenantAdmin->id,
                'note' => 'Vinculo seedado automaticamente.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        TenantStudentTraineeLink::query()->updateOrCreate(
            [
                'tenant_id' => $tenantPlataforma->id,
                'student_user_id' => $student->id,
            ],
            [
                'trainee_user_id' => $trainer->id,
                'linked_by_user_id' => $tenantAdmin->id,
                'note' => 'Vinculo seedado automaticamente.',
            ],
        );
    }
}
