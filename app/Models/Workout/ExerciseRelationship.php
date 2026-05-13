<?php

namespace App\Models\Workout;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'source_exercise_id',
    'target_exercise_id',
    'relationship_type',
    'score',
    'metadata',
])]
class ExerciseRelationship extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'score' => 'float',
        ];
    }
}
