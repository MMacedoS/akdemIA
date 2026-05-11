<?php

namespace App\Services\Preferences;

use App\Models\Preferences\Preference;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PreferenceService
{
    public function getByUser(User $user): ?Preference
    {
        return $user->preference()->first();
    }

    public function createByUser(User $user, array $data): Preference
    {
        if ($user->preference()->exists()) {
            throw ValidationException::withMessages([
                'preferences' => 'Preferences already exist for this user.',
            ]);
        }

        $data['user_id'] = $user->id;

        return Preference::query()->create($data);
    }

    public function updateByUser(User $user, array $data): ?Preference
    {
        $preference = $user->preference()->first();

        if ($preference === null) {
            return null;
        }

        $preference->fill($data);
        $preference->save();

        return $preference;
    }

    public function upsertByUser(User $user, array $data): Preference
    {
        if ($user->preference()->exists()) {
            return $this->updateByUser($user, $data) ?? $this->createByUser($user, $data);
        }

        return $this->createByUser($user, $data);
    }
}
