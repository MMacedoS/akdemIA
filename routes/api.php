<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Admin\AiLogController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\EmailCommunicationController;
use App\Http\Controllers\Api\V1\Admin\UsageController;
use App\Http\Controllers\Api\V1\Admin\UserManagementController;
use App\Http\Controllers\Api\V1\Billing\MercadoPagoWebhookController;
use App\Http\Controllers\Api\V1\Credits\CreditRequestController;
use App\Http\Controllers\Api\V1\MedicalData\MedicalDataController;
use App\Http\Controllers\Api\V1\PhysicalData\PhysicalDataController;
use App\Http\Controllers\Api\V1\Preferences\PreferencesController;
use App\Http\Controllers\Api\V1\Profile\MeController;
use App\Http\Controllers\Api\V1\Students\WorkoutController as StudentWorkoutController;
use App\Http\Controllers\Api\V1\Tenants\TenantController;
use App\Http\Controllers\Api\V1\Workouts\ExerciseMediaController;
use App\Http\Controllers\Api\V1\Workouts\ExerciseLookupController;
use App\Http\Controllers\Api\V1\Workouts\InternalExerciseCatalogController;
use App\Http\Controllers\Api\V1\Workouts\ChangeWorkoutStatusController;
use App\Http\Controllers\Api\V1\Workouts\GenerateWorkoutController;
use App\Http\Controllers\Api\V1\Workouts\WorkoutCatalogController;
use App\Http\Controllers\Api\V1\Workouts\WorkoutStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('billing/mercadopago/webhook', MercadoPagoWebhookController::class)->name('api.billing.mercadopago.webhook');

    Route::prefix('auth')->group(function () {
        Route::get('options', [AuthController::class, 'options'])->name('api.auth.options');
        Route::post('register', [AuthController::class, 'registerStudent'])->name('api.auth.register');
        Route::post('register/student', [AuthController::class, 'registerStudent'])->name('api.auth.register-student');
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('api.auth.verify-email');
        Route::post('login', [AuthController::class, 'login'])->name('api.auth.login');
        Route::post('google', [AuthController::class, 'googleLogin'])->name('api.auth.google');
        Route::post('select-tenant', [AuthController::class, 'selectTenant'])->name('api.auth.select-tenant');
    });

    Route::get('internal/catalog/exercises', [InternalExerciseCatalogController::class, 'index'])
        ->name('api.internal.catalog.exercises.index');

    Route::get('workouts/exercises/media/{workoutxName}', [ExerciseMediaController::class, 'show'])
        ->middleware('exercise.media.host')
        ->name('api.workouts.exercises.media.show');

    Route::middleware(['tenant.auth', 'tenant.user'])->group(function () {
        Route::post('auth/accept-policies', [AuthController::class, 'acceptPolicies'])->name('api.auth.accept-policies');

        Route::prefix('credits/requests')->group(function () {
            Route::get('/', [CreditRequestController::class, 'index'])->name('api.credits.requests.index');
            Route::post('/', [CreditRequestController::class, 'store'])->name('api.credits.requests.store');
            Route::get('{id}', [CreditRequestController::class, 'show'])->name('api.credits.requests.show');
        });

        Route::middleware(['policies.accepted'])->group(function () {
            Route::middleware(['subscription'])->group(function () {
                Route::get('me', [MeController::class, 'show'])->name('api.me.show');
                Route::post('me', [MeController::class, 'update'])->name('api.me.store');
                Route::put('me', [MeController::class, 'update'])->name('api.me.update');
                Route::get('me/trainers', ['App\\Http\\Controllers\\Api\\V1\\Profile\\StudentTrainerController', 'index'])->middleware(['role:student'])->name('api.me.trainers.index');
                Route::put('me/trainer', ['App\\Http\\Controllers\\Api\\V1\\Profile\\StudentTrainerController', 'update'])->middleware(['role:student'])->name('api.me.trainer.update');

                Route::middleware(['role:admin'])->group(function () {
                    Route::get('users', [UserManagementController::class, 'index'])->name('api.users.index');
                    Route::post('students', [UserManagementController::class, 'storeStudent'])->name('api.students.store');
                    Route::post('trainers', [UserManagementController::class, 'storeTrainer'])->name('api.trainers.store');
                });

                Route::get('physical-data', [PhysicalDataController::class, 'show'])->name('api.physical-data.show');
                Route::post('physical-data', [PhysicalDataController::class, 'store'])->name('api.physical-data.store');
                Route::put('physical-data', [PhysicalDataController::class, 'update'])->name('api.physical-data.update');

                Route::get('medical-data', [MedicalDataController::class, 'show'])->name('api.medical-data.show');
                Route::post('medical-data', [MedicalDataController::class, 'store'])->name('api.medical-data.store');
                Route::put('medical-data', [MedicalDataController::class, 'update'])->name('api.medical-data.update');

                Route::get('preferences', [PreferencesController::class, 'show'])->name('api.preferences.show');
                Route::post('preferences', [PreferencesController::class, 'store'])->name('api.preferences.store');
                Route::put('preferences', [PreferencesController::class, 'update'])->name('api.preferences.update');

                Route::prefix('students')->middleware(['role:student'])->group(function () {
                    Route::get('workout', [StudentWorkoutController::class, 'show'])->name('api.students.workout.show');
                    Route::get('workouts', [StudentWorkoutController::class, 'index'])->name('api.students.workouts.index');
                    Route::post('workout/generate', [StudentWorkoutController::class, 'store'])->name('api.students.workout.generate');
                    Route::post('workouts/{workoutId}/regenerate', [StudentWorkoutController::class, 'regenerate'])->name('api.students.workouts.regenerate');
                    Route::get('catalogs', [WorkoutCatalogController::class, 'index'])->name('api.students.catalogs.index');
                    Route::get('catalogs/mine', [WorkoutCatalogController::class, 'mine'])->name('api.students.catalogs.mine');
                    Route::post('catalogs/{catalogId}/link', [WorkoutCatalogController::class, 'link'])->name('api.students.catalogs.link');
                });

                Route::post('workouts/generate', [GenerateWorkoutController::class, 'store'])->name('api.workouts.generate');
                Route::post('workouts/change-status/{workoutId}', [ChangeWorkoutStatusController::class, 'update'])->name('api.workouts.change-status');
                Route::get('workouts/status/{id}', [WorkoutStatusController::class, 'show'])->name('api.workouts.status.show');
                Route::get('workouts/exercises/name/{name}', [ExerciseLookupController::class, 'show'])->name('api.workouts.exercises.show');

                Route::prefix('admin')->middleware(['role:admin'])->group(function () {
                    Route::get('dashboard', [DashboardController::class, 'show'])->name('api.admin.dashboard.show');
                    Route::get('ai-logs', [AiLogController::class, 'index'])->name('api.admin.ai-logs.index');
                    Route::get('usage', [UsageController::class, 'show'])->name('api.admin.usage.show');
                    Route::post('communications/email', [EmailCommunicationController::class, 'store'])->name('api.admin.communications.email.store');
                });
            });
        });
    });

    Route::middleware(['auth', 'tenant.user'])->group(function () {
        Route::get('tenants/select', [TenantController::class, 'index'])->name('api.tenants.select');
        Route::post('tenants/select', [TenantController::class, 'store'])->name('api.tenants.select.store');
    });
});
