<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictExerciseMediaHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host === '' || ! $this->isAllowedHost($host)) {
            abort(403, 'Host sem acesso a midia de exercicios.');
        }

        return $next($request);
    }

    private function isAllowedHost(string $host): bool
    {
        if (in_array($host, ['academai.com.br', 'api.academai.com.br', 'localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost')) {
            return true;
        }

        return false;
    }
}
