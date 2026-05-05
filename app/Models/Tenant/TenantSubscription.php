<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'plan_id',
    'stripe_subscription_id',
    'status',
    'ai_usage',
    'starts_at',
    'ends_at',
])]
class TenantSubscription extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ai_usage' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
