<?php

namespace App\Models\Landing;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'hero_title',
    'hero_description',
    'hero_image_url',
    'primary_cta_text',
    'primary_cta_url',
    'secondary_cta_text',
    'secondary_cta_url',
    'about_title',
    'about_content',
    'tenants_section_title',
    'professionals_section_title',
    'differentials_section_title',
    'contact_section_title',
    'contact_description',
    'contact_email',
    'contact_whatsapp',
])]
class SystemLandingSetting extends Model {}
