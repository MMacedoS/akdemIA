<?php

namespace App\Support\Workout;

class ExerciseAssetBuilder
{
    public function normalizeSteps(mixed $steps, string $exerciseName, string $notes = ''): array
    {
        $normalizedSteps = [];

        if (is_array($steps)) {
            $normalizedSteps = $steps;
        } elseif (is_string($steps) && trim($steps) !== '') {
            $normalizedSteps = preg_split('/(?:\r\n|\r|\n|;)+/', $steps) ?: [];
        }

        $normalizedSteps = array_values(array_filter(array_map(
            static fn(mixed $step): string => trim((string) $step),
            $normalizedSteps,
        ), static fn(string $step): bool => $step !== ''));

        if ($normalizedSteps !== []) {
            return array_slice($normalizedSteps, 0, 5);
        }

        $safeExerciseName = trim($exerciseName) !== '' ? trim($exerciseName) : 'o exercicio';

        return array_values(array_filter([
            'Posicione-se corretamente para ' . $safeExerciseName . '.',
            'Execute o movimento principal com controle e respiracao constante.',
            trim($notes) !== '' ? trim($notes) : 'Retorne a posicao inicial sem perder a postura.',
        ]));
    }

    public function resolveIllustrationSvg(
        mixed $svg,
        string $exerciseName,
        string $focus,
        array $steps,
        string $category = 'specific',
    ): string {
        $normalizedSvg = is_string($svg) ? trim($svg) : '';

        if ($this->isSafeSvgString($normalizedSvg)) {
            return $normalizedSvg;
        }

        return $this->buildFallbackSvg($exerciseName, $focus, $steps, $category);
    }

    private function isSafeSvgString(string $svg): bool
    {
        if ($svg === '' || ! str_starts_with(ltrim($svg), '<svg') || ! str_contains($svg, '</svg>')) {
            return false;
        }

        if (strlen($svg) > 20000) {
            return false;
        }

        return preg_match('/<script|on[a-z]+\s*=|javascript:|<foreignObject/i', $svg) !== 1;
    }

    private function buildFallbackSvg(string $exerciseName, string $focus, array $steps, string $category): string
    {
        $accent = $category === 'cardio' ? '#ef4444' : '#2563eb';
        $safeName = htmlspecialchars(trim($exerciseName) !== '' ? $exerciseName : 'Exercicio', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeFocus = htmlspecialchars(trim($focus) !== '' ? $focus : 'Treino', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $stepMarkup = '';

        foreach (array_slice($steps, 0, 3) as $index => $step) {
            $safeStep = htmlspecialchars(trim((string) $step), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $stepY = 208 + ($index * 22);
            $stepMarkup .= '<text x="26" y="' . $stepY . '" fill="#334155" font-size="12">' . ($index + 1) . '. ' . $safeStep . '</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 320" role="img" aria-label="Ilustracao de ' . $safeName . '">'
            . '<rect width="320" height="320" rx="24" fill="#f8fafc"/>'
            . '<rect x="18" y="18" width="284" height="284" rx="20" fill="#ffffff" stroke="#cbd5e1"/>'
            . '<text x="26" y="42" fill="#0f172a" font-size="18" font-weight="700">' . $safeName . '</text>'
            . '<text x="26" y="62" fill="#475569" font-size="12">Foco: ' . $safeFocus . '</text>'
            . '<circle cx="160" cy="98" r="18" fill="' . $accent . '" opacity="0.18"/>'
            . '<circle cx="160" cy="96" r="12" fill="' . $accent . '"/>'
            . '<line x1="160" y1="108" x2="160" y2="168" stroke="#0f172a" stroke-width="8" stroke-linecap="round"/>'
            . '<line x1="128" y1="130" x2="192" y2="126" stroke="#0f172a" stroke-width="8" stroke-linecap="round"/>'
            . '<line x1="160" y1="168" x2="132" y2="214" stroke="#0f172a" stroke-width="8" stroke-linecap="round"/>'
            . '<line x1="160" y1="168" x2="188" y2="214" stroke="#0f172a" stroke-width="8" stroke-linecap="round"/>'
            . '<path d="M110 122 C126 112, 136 108, 144 108" fill="none" stroke="' . $accent . '" stroke-width="4" stroke-linecap="round"/>'
            . '<path d="M210 122 C194 112, 184 108, 176 108" fill="none" stroke="' . $accent . '" stroke-width="4" stroke-linecap="round"/>'
            . '<rect x="24" y="192" width="272" height="92" rx="16" fill="#f8fafc" stroke="#e2e8f0"/>'
            . $stepMarkup
            . '</svg>';
    }
}
