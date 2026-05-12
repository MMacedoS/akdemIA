<?php

namespace App\Console\Commands;

use App\Services\Workouts\ExerciseCatalogService;
use Illuminate\Console\Command;

class ExportOpenAiWorkoutCatalog extends Command
{
    protected $signature = 'ai:export-workout-catalog {path? : Caminho relativo dentro do disk local}';

    protected $description = 'Exporta o catalogo local de exercicios para um JSON em storage/app, pronto para uso local ou upload posterior para a OpenAI.';

    public function handle(ExerciseCatalogService $exerciseCatalogService): int
    {
        $result = $exerciseCatalogService->exportAiCatalogDocument($this->argument('path'));
        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];

        $this->info('Arquivo exportado: storage/app/' . ($result['path'] ?? 'ai/openai-workout-catalog.json'));
        $this->line('Total de exercicios: ' . (int) ($meta['total'] ?? 0));
        $this->line('Ultima atualizacao catalogo: ' . ((string) ($meta['max_updated_at'] ?? 'n/a')));

        return self::SUCCESS;
    }
}
