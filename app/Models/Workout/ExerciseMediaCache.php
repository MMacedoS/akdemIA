<?php

namespace App\Models\Workout;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'workoutx_name',
    'query_name',
    'remote_gif_url',
    'storage_path',
    'payload',
])]
class ExerciseMediaCache extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
