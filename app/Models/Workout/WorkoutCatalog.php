<?php

namespace App\Models\Workout;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'quantity_exercises',
    'price',
    'user_id',
    'path_image',
    'is_public',
    'status',
])]
class WorkoutCatalog extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_exercises' => 'integer',
            'price' => 'integer',
            'is_public' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExerciseWorkoutCatalog::class, 'workouts_catalog_id');
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(
            ExerciseMediaCache::class,
            'exercise_workouts_catalogs',
            'workouts_catalog_id',
            'exercise_media_cache_id'
        )
            ->using(ExerciseWorkoutCatalog::class)
            ->withPivot(['id', 'order'])
            ->withTimestamps()
            ->orderBy('exercise_workouts_catalogs.order');
    }
}
