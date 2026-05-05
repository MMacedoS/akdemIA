<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
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

        $tenantCentro = Tenant::query()->firstOrCreate(
            ['slug' => 'academia-centro'],
            ['name' => 'Akademia Centro', 'is_active' => true],
        );

        $tenantNorte = Tenant::query()->firstOrCreate(
            ['slug' => 'academia-norte'],
            ['name' => 'Akademia Norte', 'is_active' => true],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@akdemia.com.br'],
            ['name' => 'Admin Geral', 'password' => $adminPassword, 'is_system_admin' => true, 'email_verified_at' => now()],
        );

        $trainer = User::query()->updateOrCreate(
            ['email' => 'trainer@akademia.test'],
            ['name' => 'Trainer Principal', 'password' => $defaultPassword],
        );

        $student = User::query()->updateOrCreate(
            ['email' => 'student@akademia.test'],
            ['name' => 'Aluno Teste', 'password' => $defaultPassword],
        );

        $multiTenantUser = User::query()->updateOrCreate(
            ['email' => 'multi@akademia.test'],
            ['name' => 'Usuario Multi Tenant', 'password' => $defaultPassword],
        );

        $admin->tenants()->detach();

        $trainer->tenants()->syncWithoutDetaching([
            $tenantCentro->id => ['role' => Role::TRAINER->value],
            $tenantNorte->id => ['role' => Role::TRAINER->value],
        ]);

        $student->tenants()->syncWithoutDetaching([
            $tenantCentro->id => ['role' => Role::STUDENT->value],
        ]);

        $multiTenantUser->tenants()->syncWithoutDetaching([
            $tenantCentro->id => ['role' => Role::STUDENT->value],
            $tenantNorte->id => ['role' => Role::ADMIN->value],
        ]);
    }
}
