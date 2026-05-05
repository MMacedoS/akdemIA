<?php

namespace App\Models\PhysicalData;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'body_fat_percentage', 'activity_level', 'imc'])]
class PhysicalData extends Model
{
    protected function casts(): array
    {
        return [
            'body_fat_percentage' => 'decimal:2',
            'imc' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
