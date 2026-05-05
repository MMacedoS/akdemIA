<?php

namespace App\Models\Landing;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'slug',
    'headline',
    'bio',
    'skills',
    'service_section_title',
    'service_one_label',
    'service_one_title',
    'service_one_description',
    'service_one_link_label',
    'service_one_link_url',
    'service_two_label',
    'service_two_title',
    'service_two_description',
    'service_two_link_label',
    'service_two_link_url',
    'service_three_label',
    'service_three_title',
    'service_three_description',
    'service_three_link_label',
    'service_three_link_url',
    'contact_whatsapp',
    'contact_instagram',
    'hero_image_url',
    'hero_video_url',
    'theme_preset',
    'is_published',
])]
class UserPublicProfile extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
