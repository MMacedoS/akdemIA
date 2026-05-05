<?php

namespace App\Models\Tenant;

use App\Models\Landing\TenantLandingPage;
use App\Models\Landing\TenantProfessionalMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'stripe_id', 'is_active', 'contact_email', 'contact_phone', 'document_number', 'notes'])]
class Tenant extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role');
    }

    public function trainees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_trainee', 'tenant_id', 'trainee_user_id')
            ->withPivot(['linked_by_user_id', 'note'])
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function landingPage(): HasOne
    {
        return $this->hasOne(TenantLandingPage::class);
    }

    public function professionalMedia(): HasMany
    {
        return $this->hasMany(TenantProfessionalMedia::class);
    }

    public function studentTraineeLinks(): HasMany
    {
        return $this->hasMany(TenantStudentTraineeLink::class);
    }

    public function hasUser(User $user): bool
    {
        return $this->users()
            ->wherePivot('tenant_id', $this->id)
            ->wherePivot('user_id', $user->id)
            ->exists();
    }
}
