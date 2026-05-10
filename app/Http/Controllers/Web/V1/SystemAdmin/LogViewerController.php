<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Services\System\SystemAdminAuditLogger;
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
        $validated = $request->validate([
            'lines' => ['nullable', 'integer', 'min:50', 'max:2000'],
        ]);

        $path = storage_path('logs/laravel.log');
        $lines = (int) ($validated['lines'] ?? 300);
        $fileExists = File::exists($path);

        return view('web.v1.system_admin.settings.logs', [
            'logContent' => $fileExists ? $this->tailFile($path, $lines) : null,
            'logExists' => $fileExists,
            'lineCount' => $lines,
            'logPath' => $path,
            'fileSize' => $fileExists ? File::size($path) : null,
            'lastModifiedAt' => $fileExists ? now()->setTimestamp(File::lastModified($path)) : null,
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $path = storage_path('logs/laravel.log');
        $fileExists = File::exists($path);
        $before = [
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
                'exists' => true,
                'size' => 0,
            ],
        );

        return redirect()
            ->route('system-admin.settings.logs.index', ['lines' => $request->integer('lines', 300)])
            ->with('status', 'laravel.log limpo com sucesso.');
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
