<?php

namespace App\Models\Workout;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workouts_catalog_id',
    'exercise_media_cache_id',
    'order',
])]
class ExerciseWorkoutCatalog extends Model
{
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(WorkoutCatalog::class, 'workouts_catalog_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(ExerciseMediaCache::class, 'exercise_media_cache_id');
    }
}
