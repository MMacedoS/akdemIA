<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price', 'max_students', 'max_trainers', 'ai_limit', 'features'])]
class Plan extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }
}
