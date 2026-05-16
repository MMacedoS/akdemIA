<?php

namespace App\Models\Workout;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'workouts_catalog_id',
    'credits_consumed',
    'linked_at',
])]
class WorkoutCatalogUserLink extends Model
{
    protected $table = 'workout_catalog_user_links';

    protected function casts(): array
    {
        return [
            'credits_consumed' => 'integer',
            'linked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(WorkoutCatalog::class, 'workouts_catalog_id');
    }
}
