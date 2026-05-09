<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Services\Tenant\PlatformTenantService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('students:migrate-standalone')]
#[Description('Migrates legacy students from tenant_user to standalone trainer linkage.')] 
class MigrateLegacyStudentsToStandalone extends Command
{
    public function handle(
        PlatformTenantService $platformTenantService,
        TraineeStudentRepositoryContract $traineeStudentRepository,
    ): int {
        $platformTrainee = $platformTenantService->resolvePlatformTrainee();
        $studentIds = DB::table('tenant_user')
            ->where('role', Role::STUDENT->value)
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id');

        $migrated = 0;
        $skipped = 0;

        foreach ($studentIds as $studentId) {
            $student = User::query()->find((int) $studentId);

            if (! $student instanceof User || $student->profileType() !== Role::STUDENT) {
                $skipped++;
                continue;
            }

            $traineeUserId = $this->resolveTargetTraineeId($student->id, $platformTrainee->id);

            DB::transaction(function () use ($student, $traineeUserId, $platformTrainee, $traineeStudentRepository): void {
                $traineeStudentRepository->reassignStudentTrainee(null, $student->id, $traineeUserId, $platformTrainee->id);

                DB::table('tenant_student_trainee_links')
                    ->where('student_user_id', $student->id)
                    ->whereNotNull('tenant_id')
                    ->delete();

                DB::table('workouts')
                    ->where('user_id', $student->id)
                    ->update([
                        'tenant_id' => null,
                        'updated_at' => now(),
                    ]);

                DB::table('tenant_user')
                    ->where('user_id', $student->id)
                    ->where('role', Role::STUDENT->value)
                    ->delete();
            });

            $migrated++;
        }

        $this->info("Standalone migration finished. Migrated: {$migrated}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function resolveTargetTraineeId(int $studentUserId, int $platformTraineeId): int
    {
        $currentStandaloneLink = DB::table('tenant_student_trainee_links')
            ->join('users', 'users.id', '=', 'tenant_student_trainee_links.trainee_user_id')
            ->where('tenant_student_trainee_links.student_user_id', $studentUserId)
            ->whereNull('tenant_student_trainee_links.tenant_id')
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->orderByDesc('tenant_student_trainee_links.id')
            ->value('tenant_student_trainee_links.trainee_user_id');

        if (is_numeric($currentStandaloneLink)) {
            return (int) $currentStandaloneLink;
        }

        $legacyLink = DB::table('tenant_student_trainee_links')
            ->join('users', 'users.id', '=', 'tenant_student_trainee_links.trainee_user_id')
            ->where('tenant_student_trainee_links.student_user_id', $studentUserId)
            ->whereNotNull('tenant_student_trainee_links.tenant_id')
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->orderByDesc('tenant_student_trainee_links.updated_at')
            ->orderByDesc('tenant_student_trainee_links.id')
            ->value('tenant_student_trainee_links.trainee_user_id');

        return is_numeric($legacyLink) ? (int) $legacyLink : $platformTraineeId;
    }
}