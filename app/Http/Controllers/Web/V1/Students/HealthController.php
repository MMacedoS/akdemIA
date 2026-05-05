<?php

namespace App\Http\Controllers\Web\V1\Students;

use App\Http\Controllers\Controller;
use App\Models\MedicalData\MedicalData;
use App\Models\PhysicalData\PhysicalData;
use App\Models\Preferences\Preference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->loadMissing(['physicalData', 'medicalData', 'preference']);

        return view('web.v1.students.health.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $payload = $request->validate([
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'height' => ['nullable', 'numeric', 'min:0.5', 'max:3'],
            'weight' => ['nullable', 'numeric', 'min:20', 'max:500'],
            'body_fat_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'activity_level' => ['nullable', 'string', 'max:255'],
            'injuries' => ['nullable', 'string', 'max:1200'],
            'diseases' => ['nullable', 'string', 'max:1200'],
            'medications' => ['nullable', 'string', 'max:1200'],
            'restrictions' => ['nullable', 'string', 'max:1200'],
            'preferred_foods' => ['nullable', 'string', 'max:2000'],
            'disliked_foods' => ['nullable', 'string', 'max:2000'],
            'drinks' => ['nullable', 'string', 'max:2000'],
            'available_hours' => ['nullable', 'string', 'max:2000'],
            'training_frequency' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill([
            'birth_date' => $payload['birth_date'] ?? null,
            'height' => $payload['height'] ?? null,
            'weight' => $payload['weight'] ?? null,
        ]);
        $user->save();

        $imc = $this->calculateImc($user->height, $user->weight);

        PhysicalData::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'body_fat_percentage' => $payload['body_fat_percentage'] ?? null,
                'activity_level' => $payload['activity_level'] ?? null,
                'imc' => $imc,
            ]
        );

        MedicalData::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'injuries' => $payload['injuries'] ?? null,
                'diseases' => $payload['diseases'] ?? null,
                'medications' => $payload['medications'] ?? null,
                'restrictions' => $payload['restrictions'] ?? null,
            ]
        );

        Preference::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_foods' => $this->parseCsvToArray($payload['preferred_foods'] ?? null),
                'disliked_foods' => $this->parseCsvToArray($payload['disliked_foods'] ?? null),
                'drinks' => $this->parseCsvToArray($payload['drinks'] ?? null),
                'available_hours' => $this->parseCsvToArray($payload['available_hours'] ?? null),
                'training_frequency' => $payload['training_frequency'] ?? null,
            ]
        );

        return redirect()->route('students.health.edit')->with('status', 'Dados de saude atualizados com sucesso.');
    }

    private function parseCsvToArray(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn($item) => trim($item))
            ->filter(fn($item) => $item !== '')
            ->values()
            ->all();
    }

    private function calculateImc(mixed $height, mixed $weight): ?float
    {
        if (! is_numeric($height) || ! is_numeric($weight)) {
            return null;
        }

        $normalizedHeight = (float) $height;
        $normalizedWeight = (float) $weight;

        if ($normalizedHeight <= 0 || $normalizedWeight <= 0) {
            return null;
        }

        return round($normalizedWeight / ($normalizedHeight * $normalizedHeight), 2);
    }
}
