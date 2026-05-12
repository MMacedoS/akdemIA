<?php

namespace App\Models\AI;

use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'user_id',
    'type',
    'operation',
    'provider',
    'model',
    'prompt_hash',
    'request_hash',
    'response_size',
    'cache_key',
    'cache_hit',
    'retrieval_mode',
    'vector_store_id',
    'file_id',
    'http_status',
    'latency_ms',
    'prompt_tokens',
    'completion_tokens',
    'total_tokens',
    'metadata',
])]
class AiLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'cache_hit' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
