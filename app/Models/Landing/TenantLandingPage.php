<?php

namespace App\Models\Landing;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'title',
    'headline',
    'description',
    'hero_image_url',
    'hero_video_url',
    'theme_preset',
    'primary_color',
    'secondary_color',
    'cta_text',
    'cta_url',
    'instagram_username',
    'instagram_access_token',
    'is_published',
])]
class TenantLandingPage extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
