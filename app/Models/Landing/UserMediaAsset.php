<?php

namespace App\Models\Landing;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'media_type',
    'media_url',
    'title',
    'description',
    'sort_order',
])]
class UserMediaAsset extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
