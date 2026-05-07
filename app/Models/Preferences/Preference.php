<?php

namespace App\Models\Preferences;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'preferred_foods',
    'disliked_foods',
    'drinks',
    'available_hours',
    'training_frequency',
])]
class Preference extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'preferred_foods' => 'array',
            'disliked_foods' => 'array',
            'drinks' => 'array',
            'available_hours' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
