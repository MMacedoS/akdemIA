<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_user_id',
    'domain',
    'action',
    'auditable_type',
    'auditable_id',
    'before_data',
    'after_data',
    'metadata',
])]
class SystemAdminAuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'metadata' => 'array',
        ];
    }
}
