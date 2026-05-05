<?php

namespace App\Models\Workout;

use App\Models\User;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'user_id',
    'status',
    'request_status',
    'regeneration_request',
    'workout_plan',
    'meal_plan',
    'recommendations',
    'cardio_plan',
    'safety_flags',
])]
class Workout extends Model
{
    protected function casts(): array
    {
        return [
            'workout_plan' => 'array',
            'meal_plan' => 'array',
            'recommendations' => 'array',
            'cardio_plan' => 'array',
            'safety_flags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
