<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'catalog_type',
    'vector_store_id',
    'vector_store_name',
    'file_id',
    'storage_disk',
    'storage_path',
    'source_hash',
    'status',
    'last_synced_at',
    'last_used_at',
    'metadata',
])]
class AiVectorStore extends Model
{
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
