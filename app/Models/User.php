<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Landing\UserMediaAsset;
use App\Models\Landing\UserPost;
use App\Models\Landing\UserPublicProfile;
use App\Models\MedicalData\MedicalData;
use App\Models\PhysicalData\PhysicalData;
use App\Models\Preferences\Preference;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\Workout\Workout;
use Illuminate\Auth\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'birth_date',
    'gender',
    'height',
    'weight',
    'goal',
    'avatar_path',
    'credits_balance',
    'is_active',
    'is_system_admin',
    'profile_type',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, Billable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'birth_date' => 'date',
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
            'credits_balance' => 'integer',
            'is_active' => 'boolean',
            'is_system_admin' => 'boolean',
        ];
    }

    public function profileType(): ?Role
    {
        return Role::tryFrom((string) $this->profile_type);
    }

    public function isTrainer(): bool
    {
        return $this->profileType() === Role::TRAINER;
    }

    public function isTrainee(): bool
    {
        return $this->isTrainer() || (string) $this->profile_type === 'trainee';
    }

    public function isSystemAdmin(): bool
    {
        return (bool) $this->is_system_admin;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role');
    }

    public function traineeTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_trainee', 'trainee_user_id', 'tenant_id')
            ->withPivot(['linked_by_user_id', 'note'])
            ->withTimestamps();
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()
            ->wherePivot('tenant_id', $tenant->id)
            ->wherePivot('user_id', $this->id)
            ->exists();
    }

    public function getRole(Tenant $tenant): ?Role
    {
        $linkedTenant = $this->tenants()
            ->wherePivot('tenant_id', $tenant->id)
            ->wherePivot('user_id', $this->id)
            ->first();

        if ($linkedTenant === null || ! isset($linkedTenant->pivot->role)) {
            return null;
        }

        return Role::tryFrom((string) $linkedTenant->pivot->role);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function physicalData(): HasOne
    {
        return $this->hasOne(PhysicalData::class);
    }

    public function medicalData(): HasOne
    {
        return $this->hasOne(MedicalData::class);
    }

    public function preference(): HasOne
    {
        return $this->hasOne(Preference::class);
    }

    public function publicProfile(): HasOne
    {
        return $this->hasOne(UserPublicProfile::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(UserMediaAsset::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(UserPost::class);
    }

    public function traineeStudentLinks(): HasMany
    {
        return $this->hasMany(TenantStudentTraineeLink::class, 'trainee_user_id');
    }

    public function assignedTraineeLinks(): HasMany
    {
        return $this->hasMany(TenantStudentTraineeLink::class, 'student_user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        try {
            $this->notify(new ResetPassword($token));
        } catch (\Throwable $exception) {
            Log::error('Falha ao enviar e-mail de recuperacao de senha.', [
                'user_id' => $this->id,
                'email' => $this->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
