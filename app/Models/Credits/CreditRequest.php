<?php

namespace App\Models\Credits;

use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'requester_user_id',
    'target_user_id',
    'tenant_id',
    'credits_requested',
    'pix_key',
    'pix_payload',
    'qr_code_url',
    'payment_external_reference',
    'payment_provider_payment_id',
    'payment_ticket_url',
    'payment_status',
    'payment_status_detail',
    'payment_payload',
    'status',
    'reviewed_by_user_id',
    'reviewed_at',
    'note',
])]
class CreditRequest extends Model
{
    protected function casts(): array
    {
        return [
            'credits_requested' => 'integer',
            'payment_payload' => 'array',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
