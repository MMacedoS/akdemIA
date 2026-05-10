<?php

namespace App\Repositories\Contracts\SystemAdmin;

use Illuminate\Support\Collection;

interface LegalSettingsRepositoryContract
{
    public function values(): Collection;

    /**
     * @param  array<string, array<string, string|null>>  $documents
     */
    public function update(array $documents): void;
}
