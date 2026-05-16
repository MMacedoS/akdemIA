<?php

namespace App\Transformers\Workout;

use App\Models\Workout\WorkoutCatalog;

class WorkoutCatalogTransformer
{
    public function transform(WorkoutCatalog $catalog, bool $isLinked): array
    {
        return [
            'id' => (int) $catalog->id,
            'name' => (string) $catalog->name,
            'description' => (string) $catalog->description,
            'quantity_exercises' => (int) $catalog->quantity_exercises,
            'price' => (int) $catalog->price,
            'credit_price' => (int) $catalog->price,
            'is_public' => (bool) $catalog->is_public,
            'status' => (bool) $catalog->status,
            'path_image' => $catalog->path_image,
            'owner' => [
                'id' => $catalog->owner?->id === null ? null : (int) $catalog->owner?->id,
                'name' => $catalog->owner?->name,
            ],
            'is_linked' => $isLinked,
        ];
    }
}
