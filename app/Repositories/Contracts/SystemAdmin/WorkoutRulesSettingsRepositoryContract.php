<?php

namespace App\Repositories\Contracts\SystemAdmin;

use Illuminate\Support\Collection;

interface WorkoutRulesSettingsRepositoryContract
{
    /**
     * @return Collection<string, string|null>
     */
    public function values(): Collection;

    public function update(array $payload): void;
}
