<?php

namespace App\Models\MedicalData;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'injuries', 'diseases', 'medications', 'restrictions'])]
class MedicalData extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
