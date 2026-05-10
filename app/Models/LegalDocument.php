<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'title',
    'slug',
    'version',
    'effective_date',
    'content_html',
    'is_active',
])]
class LegalDocument extends Model
{
    public const TYPE_TERMS = 'terms';
    public const TYPE_PRIVACY_POLICY = 'privacy_policy';

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
