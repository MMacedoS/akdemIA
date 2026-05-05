<?php

use App\Enums\Role;
use App\Http\Controllers\Web\V1\Admin\DashboardController;
use App\Http\Controllers\Web\V1\Admin\CreditRequestController as AdminCreditRequestController;
use App\Http\Controllers\Web\V1\Admin\TenantLandingController;
use App\Http\Controllers\Web\V1\Admin\TraineesController;
use App\Http\Controllers\Web\V1\Landing\PublicLandingController;
use App\Http\Controllers\Web\V1\Landing\SystemLandingController;
use App\Http\Controllers\Web\V1\SystemAdmin\DashboardController as SystemAdminDashboardController;
use App\Http\Controllers\Web\V1\SystemAdmin\CreditController as SystemAdminCreditController;
use App\Http\Controllers\Web\V1\SystemAdmin\AuthController as SystemAdminAuthController;
use App\Http\Controllers\Web\V1\SystemAdmin\SystemLandingController as SystemAdminSystemLandingController;
use App\Http\Controllers\Web\V1\SystemAdmin\CreditOverviewController as SystemAdminCreditOverviewController;
use App\Http\Controllers\Web\V1\SystemAdmin\EmailSettingsController as SystemAdminEmailSettingsController;
use App\Http\Controllers\Web\V1\SystemAdmin\PaymentSettingsController as SystemAdminPaymentSettingsController;
use App\Http\Controllers\Web\V1\SystemAdmin\WorkoutxSettingsController as SystemAdminWorkoutxSettingsController;
use App\Http\Controllers\Web\V1\SystemAdmin\TraineeManagementController as SystemAdminTraineeManagementController;
use App\Http\Controllers\Web\V1\SystemAdmin\TenantManagementController as SystemAdminTenantManagementController;
use App\Http\Controllers\Web\V1\SystemAdmin\UserManagementController as SystemAdminUserManagementController;
use App\Http\Controllers\Web\V1\Admin\StudentsController;
use App\Http\Controllers\Web\V1\Admin\TrainersController;
use App\Http\Controllers\Web\V1\Admin\UsersController;
use App\Http\Controllers\Web\V1\Students\DashboardController as StudentDashboardController;
use App\Http\Controllers\Web\V1\Students\HealthController as StudentHealthController;
use App\Http\Controllers\Web\V1\Students\WorkoutController as StudentWorkoutController;
use App\Http\Controllers\Web\V1\Tenants\TenantSelectionController;
use App\Http\Controllers\Web\V1\Trainee\DashboardController as TraineeDashboardController;
use App\Http\Controllers\Web\V1\Trainee\CreditRequestController as TraineeCreditRequestController;
use App\Http\Controllers\Web\V1\Trainee\StudentsController as TraineeStudentsController;
use App\Http\Controllers\Web\V1\Trainer\DashboardController as TrainerDashboardController;
use App\Http\Controllers\Web\V1\Trainer\StudentsController as TrainerStudentsController;
use App\Http\Controllers\Web\V1\Users\MyLandingController;
use App\Models\Tenant\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', SystemLandingController::class)->name('home');
Route::get('/pro/{slug}', [PublicLandingController::class, 'user'])->name('landing.user');

$configuredLandingDomain = env('APP_LANDING_ROOT_DOMAIN');
$appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
$landingRootDomain = is_string($configuredLandingDomain) && $configuredLandingDomain !== ''
    ? $configuredLandingDomain
    : ((is_string($appHost) && $appHost !== '' && $appHost !== 'localhost') ? $appHost : 'academai.com.br');

if (is_string($landingRootDomain) && $landingRootDomain !== '') {
    Route::domain('{slug}.' . $landingRootDomain)->group(function () {
        Route::get('/', [PublicLandingController::class, 'subdomain'])->name('landing.subdomain');
    });
}

Route::prefix('system-admin')->name('system-admin.')->middleware('guest:web')->group(function () {
    Route::get('/login', [SystemAdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SystemAdminAuthController::class, 'login'])->name('login.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('my-landing', [MyLandingController::class, 'edit'])->name('my-landing.edit');
    Route::put('my-landing', [MyLandingController::class, 'update'])->name('my-landing.update');
    Route::post('my-landing/media', [MyLandingController::class, 'storeMedia'])->name('my-landing.media.store');
    Route::delete('my-landing/media/{mediaId}', [MyLandingController::class, 'destroyMedia'])->name('my-landing.media.destroy');
    Route::post('my-landing/posts', [MyLandingController::class, 'storePost'])->name('my-landing.posts.store');
    Route::put('my-landing/posts/{postId}', [MyLandingController::class, 'updatePost'])->name('my-landing.posts.update');
    Route::delete('my-landing/posts/{postId}', [MyLandingController::class, 'destroyPost'])->name('my-landing.posts.destroy');

    Route::get('tenants/select', [TenantSelectionController::class, 'index'])->name('tenants.select');
    Route::post('tenants/select', [TenantSelectionController::class, 'store'])->name('tenants.select.store');

    Route::get('dashboard', function (Request $request) {
        if ((bool) $request->user()?->isSystemAdmin()) {
            return redirect()->route('system-admin.dashboard');
        }

        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            if ($request->user()?->isTrainee()) {
                return redirect()->route('trainee.dashboard');
            }

            if ($request->user()?->profileType() === Role::STUDENT) {
                return redirect()->route('students.dashboard');
            }

            return redirect()->route('tenants.select');
        }

        $role = $request->user()?->getRole($tenant);

        return match (true) {
            $role === Role::ADMIN => redirect()->route('admin.dashboard'),
            $role === Role::TRAINER => redirect()->route('trainee.dashboard'),
            $role === Role::STUDENT => redirect()->route('students.dashboard'),
            $request->user()?->isTrainee() && $request->user()?->traineeTenants()->where('tenants.id', $tenant->id)->exists() => redirect()->route('trainee.dashboard'),
            default => abort(403, 'Perfil sem acesso para o tenant selecionado.'),
        };
    })->name('dashboard');

    Route::prefix('trainee')->name('trainee.')->group(function () {
        Route::get('/dashboard', [TraineeDashboardController::class, 'index'])->name('dashboard');
        Route::get('/credits', [TraineeCreditRequestController::class, 'index'])->name('credits.index');
        Route::post('/credits', [TraineeCreditRequestController::class, 'store'])->name('credits.store');
        Route::get('/students', [TraineeStudentsController::class, 'index'])->name('students.index');
        Route::get('/students/create', [TraineeStudentsController::class, 'create'])->name('students.create');
        Route::post('/students', [TraineeStudentsController::class, 'store'])->name('students.store');
        Route::get('/students/{id}', [TraineeStudentsController::class, 'show'])->name('students.show');
        Route::post('/students/{id}/workouts/generate', [TraineeStudentsController::class, 'generateWorkout'])->name('students.workouts.generate');
        Route::get('/students/{id}/workouts/{workoutId}', [TraineeStudentsController::class, 'showWorkout'])->name('students.workouts.show');
        Route::post('/students/{id}/workouts/{workoutId}/reuse', [TraineeStudentsController::class, 'reuseWorkout'])->name('students.workouts.reuse');
        Route::post('/students/{id}/workouts/{workoutId}/activate', [TraineeStudentsController::class, 'activateWorkout'])->name('students.workouts.activate');
        Route::post('/students/{id}/workouts/{workoutId}/inactivate', [TraineeStudentsController::class, 'inactivateWorkout'])->name('students.workouts.inactivate');
        Route::post('/students/{id}/workouts/{workoutId}/retry', [TraineeStudentsController::class, 'retryWorkout'])->name('students.workouts.retry');
        Route::put('/students/{id}/workouts/{workoutId}/board', [TraineeStudentsController::class, 'updateWorkoutBoard'])->name('students.workouts.board.update');
        Route::post('/students/{id}/workouts/{workoutId}/regenerate', [TraineeStudentsController::class, 'regenerateWorkout'])->name('students.workouts.regenerate');
        Route::get('/students/{id}/edit', [TraineeStudentsController::class, 'edit'])->name('students.edit');
        Route::put('/students/{id}', [TraineeStudentsController::class, 'update'])->name('students.update');
    });

    Route::prefix('trainer')->name('trainer.')->middleware(['role:trainer'])->group(function () {
        Route::get('/dashboard', [TrainerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/students', [TrainerStudentsController::class, 'index'])->name('students.index');
        Route::get('/students/{id}', [TrainerStudentsController::class, 'show'])->name('students.show');
        Route::post('/students/{id}/workouts/generate', [TrainerStudentsController::class, 'generateWorkout'])->name('students.workouts.generate');
        Route::get('/students/{id}/workouts/{workoutId}', [TrainerStudentsController::class, 'showWorkout'])->name('students.workouts.show');
        Route::post('/students/{id}/workouts/{workoutId}/reuse', [TrainerStudentsController::class, 'reuseWorkout'])->name('students.workouts.reuse');
        Route::post('/students/{id}/workouts/{workoutId}/activate', [TrainerStudentsController::class, 'activateWorkout'])->name('students.workouts.activate');
        Route::post('/students/{id}/workouts/{workoutId}/inactivate', [TrainerStudentsController::class, 'inactivateWorkout'])->name('students.workouts.inactivate');
        Route::post('/students/{id}/workouts/{workoutId}/retry', [TrainerStudentsController::class, 'retryWorkout'])->name('students.workouts.retry');
        Route::put('/students/{id}/workouts/{workoutId}/board', [TrainerStudentsController::class, 'updateWorkoutBoard'])->name('students.workouts.board.update');
        Route::post('/students/{id}/workouts/{workoutId}/regenerate', [TrainerStudentsController::class, 'regenerateWorkout'])->name('students.workouts.regenerate');
        Route::get('/students/{id}/edit', [TrainerStudentsController::class, 'edit'])->name('students.edit');
        Route::put('/students/{id}', [TrainerStudentsController::class, 'update'])->name('students.update');
    });

    Route::prefix('students')->name('students.')->middleware(['role:student'])->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/health', [StudentHealthController::class, 'edit'])->name('health.edit');
        Route::put('/health', [StudentHealthController::class, 'update'])->name('health.update');
        Route::get('/workout', [StudentWorkoutController::class, 'show'])->name('workout.show');
        Route::get('/workout/start', [StudentWorkoutController::class, 'start'])->name('workout.start');
        Route::post('/workout/{workoutId}/activate', [StudentWorkoutController::class, 'activate'])->name('workout.activate');
        Route::post('/workout/{workoutId}/inactivate', [StudentWorkoutController::class, 'inactivate'])->name('workout.inactivate');
    });

    Route::prefix('admin')->name('admin.')->middleware(['role:admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/landing', [TenantLandingController::class, 'edit'])->name('landing.edit');
        Route::put('/landing', [TenantLandingController::class, 'update'])->name('landing.update');
        Route::post('/landing/professional-media', [TenantLandingController::class, 'storeProfessionalMedia'])->name('landing.professional-media.store');
        Route::delete('/landing/professional-media/{mediaId}', [TenantLandingController::class, 'destroyProfessionalMedia'])->name('landing.professional-media.destroy');
        Route::get('/credits', [AdminCreditRequestController::class, 'index'])->name('credits.index');
        Route::post('/credits', [AdminCreditRequestController::class, 'store'])->name('credits.store');

        Route::controller(UsersController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->name('show');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::put('/{id}', 'update')->name('update');
        });

        Route::controller(StudentsController::class)->prefix('students')->name('students.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->name('show');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::put('/{id}', 'update')->name('update');
        });

        Route::controller(TraineesController::class)->prefix('trainees')->name('trainees.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->name('show');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::put('/{id}', 'update')->name('update');
        });

        Route::controller(TrainersController::class)->prefix('trainers')->name('trainers.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->name('show');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::put('/{id}', 'update')->name('update');
        });
    });

    Route::prefix('system-admin')->name('system-admin.')->middleware(['system.admin'])->group(function () {
        Route::get('/dashboard', [SystemAdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/users', [SystemAdminUserManagementController::class, 'index'])->name('users.index');
        Route::post('/users/{id}/activate', [SystemAdminUserManagementController::class, 'activate'])->name('users.activate');
        Route::post('/users/{id}/inactivate', [SystemAdminUserManagementController::class, 'inactivate'])->name('users.inactivate');
        Route::delete('/users/{id}', [SystemAdminUserManagementController::class, 'destroy'])->name('users.destroy');

        Route::get('/tenants', [SystemAdminTenantManagementController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [SystemAdminTenantManagementController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{id}/edit', [SystemAdminTenantManagementController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{id}', [SystemAdminTenantManagementController::class, 'update'])->name('tenants.update');
        Route::delete('/tenants/{id}', [SystemAdminTenantManagementController::class, 'destroy'])->name('tenants.destroy');

        Route::get('/trainees', [SystemAdminTraineeManagementController::class, 'index'])->name('trainees.index');
        Route::post('/trainees', [SystemAdminTraineeManagementController::class, 'store'])->name('trainees.store');
        Route::post('/trainees/links', [SystemAdminTraineeManagementController::class, 'linkTenant'])->name('trainees.links.store');

        Route::get('/credits', [SystemAdminCreditOverviewController::class, 'index'])->name('credits.index');

        Route::get('/settings/payment', [SystemAdminPaymentSettingsController::class, 'edit'])->name('settings.payment.edit');
        Route::put('/settings/payment', [SystemAdminPaymentSettingsController::class, 'update'])->name('settings.payment.update');

        Route::get('/settings/email', [SystemAdminEmailSettingsController::class, 'edit'])->name('settings.email.edit');
        Route::put('/settings/email', [SystemAdminEmailSettingsController::class, 'update'])->name('settings.email.update');

        Route::get('/settings/workoutx', [SystemAdminWorkoutxSettingsController::class, 'edit'])->name('settings.workoutx.edit');
        Route::put('/settings/workoutx', [SystemAdminWorkoutxSettingsController::class, 'update'])->name('settings.workoutx.update');

        Route::get('/landing', [SystemAdminSystemLandingController::class, 'edit'])->name('landing.edit');
        Route::put('/landing', [SystemAdminSystemLandingController::class, 'update'])->name('landing.update');
        Route::post('/logout', [SystemAdminAuthController::class, 'logout'])->name('logout');
        Route::post('/credits/grant', [SystemAdminCreditController::class, 'grant'])->name('credits.grant');
        Route::post('/requests/{id}/approve', [SystemAdminCreditController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{id}/reject', [SystemAdminCreditController::class, 'reject'])->name('requests.reject');
    });
});

require __DIR__ . '/settings.php';
