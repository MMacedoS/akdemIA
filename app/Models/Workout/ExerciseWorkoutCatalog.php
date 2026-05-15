<?php

namespace App\Models\Workout;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'workouts_catalog_id',
    'exercise_media_cache_id',
    'order',
])]
class ExerciseWorkoutCatalog extends Pivot
{
    protected $table = 'exercise_workouts_catalogs';

    public $incrementing = true;
    public $timestamps = true;

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(WorkoutCatalog::class, 'workouts_catalog_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(ExerciseMediaCache::class, 'exercise_media_cache_id');
    }
}
