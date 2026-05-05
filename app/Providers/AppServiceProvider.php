<?php

namespace App\Providers;

use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\SystemAdmin\EmailSettingsRepositoryContract;
use App\Repositories\Contracts\SystemAdmin\PaymentSettingsRepositoryContract;
use App\Repositories\Contracts\SystemAdmin\WorkoutxSettingsRepositoryContract;
use App\Repositories\Contracts\SystemAdmin\TenantManagementRepositoryContract;
use App\Repositories\Contracts\SystemAdmin\TraineeManagementRepositoryContract;
use App\Repositories\Contracts\Tenant\TenantTraineeRepositoryContract;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Observers\Tenant\TenantObserver;
use App\Repositories\Contracts\Tenant\TenantRepositoryContract;
use App\Repositories\Entities\SystemAdmin\EmailSettingsRepository;
use App\Repositories\Entities\SystemAdmin\PaymentSettingsRepository;
use App\Repositories\Entities\SystemAdmin\WorkoutxSettingsRepository;
use App\Repositories\Entities\SystemAdmin\TenantManagementRepository;
use App\Repositories\Entities\SystemAdmin\TraineeManagementRepository;
use App\Repositories\Entities\Tenant\TenantTraineeRepository;
use App\Repositories\Entities\Tenant\TenantRepository;
use App\Repositories\Entities\Tenant\TraineeStudentRepository;
use App\Services\Billing\PaymentConfigService;
use App\Services\System\SystemSettingsRuntimeService;
use App\Services\Tenant\TenantManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->bind(TenantRepositoryContract::class, TenantRepository::class);
        $this->app->bind(TenantTraineeRepositoryContract::class, TenantTraineeRepository::class);
        $this->app->bind(TraineeStudentRepositoryContract::class, TraineeStudentRepository::class);
        $this->app->bind(TenantManagementRepositoryContract::class, TenantManagementRepository::class);
        $this->app->bind(TraineeManagementRepositoryContract::class, TraineeManagementRepository::class);
        $this->app->bind(PaymentSettingsRepositoryContract::class, PaymentSettingsRepository::class);
        $this->app->bind(EmailSettingsRepositoryContract::class, EmailSettingsRepository::class);
        $this->app->bind(WorkoutxSettingsRepositoryContract::class, WorkoutxSettingsRepository::class);
        $this->app->singleton(PaymentConfigService::class);
        $this->app->singleton(SystemSettingsRuntimeService::class);
        $this->app->bind('currentTenant', fn() => $this->app->make(TenantManager::class)->getCurrentTenant());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(SystemSettingsRuntimeService::class)->apply();

        Tenant::observe(TenantObserver::class);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null,
        );
    }
}
