<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class LogViewerController extends Controller
{
    public function __construct(
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $availableLogs = $this->availableLogs();

        $validated = $request->validate([
            'file' => ['nullable', 'string', 'max:255'],
            'lines' => ['nullable', 'integer', 'min:50', 'max:2000'],
            'level' => ['nullable', 'string', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $selectedFile = (string) ($validated['file'] ?? 'laravel.log');
        $selectedLog = $availableLogs->firstWhere('name', $selectedFile)
            ?? $availableLogs->firstWhere('name', 'laravel.log')
            ?? $availableLogs->first();
        $lines = (int) ($validated['lines'] ?? 300);
        $level = $validated['level'] ?? null;
        $date = $validated['date'] ?? null;
        $search = trim((string) ($validated['search'] ?? ''));
        $path = $selectedLog['path'] ?? storage_path('logs/laravel.log');
        $fileExists = File::exists($path);
        $rawContent = $fileExists ? $this->tailFile($path, $this->readWindow($lines, $level, $date, $search)) : null;
        $filteredContent = $rawContent !== null
            ? $this->filterLogContent($rawContent, $lines, $level, $date, $search)
            : null;

        return view('web.v1.system_admin.settings.logs', $this->viewData([
            'availableLogs' => $availableLogs,
            'selectedFile' => $selectedLog['name'] ?? 'laravel.log',
            'logContent' => $filteredContent,
            'logExists' => $fileExists,
            'lineCount' => $lines,
            'level' => $level,
            'date' => $date,
            'search' => $search,
            'logPath' => $path,
            'fileSize' => $fileExists ? File::size($path) : null,
            'lastModifiedAt' => $fileExists ? now()->setTimestamp(File::lastModified($path)) : null,
        ]));
    }

    public function clear(Request $request): RedirectResponse
    {
        $availableLogs = $this->availableLogs();
        $selectedFile = (string) $request->input('file', 'laravel.log');
        $selectedLog = $availableLogs->firstWhere('name', $selectedFile)
            ?? $availableLogs->firstWhere('name', 'laravel.log');
        $path = $selectedLog['path'] ?? storage_path('logs/laravel.log');
        $fileExists = File::exists($path);
        $before = [
            'file' => basename($path),
            'exists' => $fileExists,
            'size' => $fileExists ? File::size($path) : 0,
        ];

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');

        $this->auditLogger->log(
            $request->user()?->id,
            'laravel_log',
            'cleared',
            null,
            $before,
            [
                'file' => basename($path),
                'exists' => true,
                'size' => 0,
            ],
        );

        return redirect()
            ->route('system-admin.settings.logs.index', [
                'file' => basename($path),
                'lines' => $request->integer('lines', 300),
                'level' => $request->string('level')->toString(),
                'date' => $request->string('date')->toString(),
                'search' => $request->string('search')->toString(),
            ])
            ->with('status', basename($path) . ' limpo com sucesso.');
    }

    private function availableLogs(): Collection
    {
        $directory = storage_path('logs');

        if (! File::isDirectory($directory)) {
            return collect();
        }

        return collect(File::files($directory))
            ->filter(fn($file): bool => strtolower($file->getExtension()) === 'log')
            ->map(fn($file): array => [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'last_modified_at' => now()->setTimestamp($file->getMTime()),
            ])
            ->sortByDesc('last_modified_at')
            ->values();
    }

    private function viewData(array $data = []): array
    {
        return array_merge([
            'availableLogs' => collect(),
            'selectedFile' => 'laravel.log',
            'lineCount' => 300,
            'level' => '',
            'date' => '',
            'search' => '',
            'logExists' => false,
            'logContent' => null,
            'logPath' => storage_path('logs/laravel.log'),
            'fileSize' => null,
            'lastModifiedAt' => null,
        ], $data);
    }

    private function readWindow(int $lines, ?string $level, ?string $date, string $search): int
    {
        if ($level === null && $date === null && $search === '') {
            return $lines;
        }

        return min(max($lines * 8, 2000), 20000);
    }

    private function filterLogContent(string $content, int $lines, ?string $level, ?string $date, string $search): string
    {
        $entries = preg_split("/\r\n|\r|\n/", trim($content));

        if (! is_array($entries) || $entries === []) {
            return '';
        }

        $filtered = array_filter($entries, function (string $line) use ($level, $date, $search): bool {
            if ($level !== null && ! str_contains(strtoupper($line), '.' . strtoupper($level) . ':')) {
                return false;
            }

            if ($date !== null && ! str_contains($line, $date)) {
                return false;
            }

            if ($search !== '' && ! str_contains(mb_strtolower($line), mb_strtolower($search))) {
                return false;
            }

            return true;
        });

        return implode(PHP_EOL, array_slice(array_values($filtered), -$lines));
    }

    private function tailFile(string $path, int $lines): string
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 'Nao foi possivel abrir o arquivo de log.';
        }

        $buffer = '';
        $chunkSize = 8192;
        $position = -1;
        $lineBreaks = 0;

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        if ($fileSize === 0) {
            fclose($handle);

            return '';
        }

        while (-$position < $fileSize) {
            $seek = max(-$fileSize, $position - $chunkSize + 1);
            $bytesToRead = abs($position - $seek) + 1;

            fseek($handle, $seek, SEEK_END);
            $chunk = fread($handle, $bytesToRead);

            if ($chunk === false) {
                break;
            }

            $buffer = $chunk . $buffer;
            $lineBreaks = substr_count($buffer, "\n");

            if ($lineBreaks > $lines) {
                break;
            }

            $position = $seek - 1;
        }

        fclose($handle);

        $entries = preg_split("/\r\n|\r|\n/", trim($buffer));

        if (! is_array($entries) || $entries === []) {
            return '';
        }

        return implode(PHP_EOL, array_slice($entries, -$lines));
    }
}
