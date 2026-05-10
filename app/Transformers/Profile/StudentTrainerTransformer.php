<?php

namespace App\Transformers\Profile;

use App\Models\User;

class StudentTrainerTransformer
{
    /**
     * @return array{id:int,name:string,email:string,is_current:bool,landing_url:?string}
     */
    public function transform(User $trainee, bool $isCurrent = false): array
    {
        $trainee->loadMissing('publicProfile:user_id,slug,is_published');

        return [
            'id' => $trainee->id,
            'name' => $trainee->name,
            'email' => $trainee->email,
            'is_current' => $isCurrent,
            'landing_url' => $this->resolveLandingUrl($trainee),
        ];
    }

    /**
     * @return array{id:int,name:string,email:string,landing_url:?string}
     */
    public function transformAssigned(User $trainee): array
    {
        return [
            'id' => $trainee->id,
            'name' => $trainee->name,
            'email' => $trainee->email,
            'landing_url' => $this->resolveLandingUrl($trainee),
        ];
    }

    private function resolveLandingUrl(User $trainee): ?string
    {
        $profile = $trainee->publicProfile;

        if ($profile === null || ! $profile->is_published || ! filled($profile->slug)) {
            return null;
        }

        return route('landing.user', ['slug' => $profile->slug]);
    }
}
