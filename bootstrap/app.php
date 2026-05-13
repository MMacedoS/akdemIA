<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\RestrictExerciseMediaHost;
use App\Http\Middleware\EnsureTenantUserAssociation;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => 'App\\Http\\Middleware\\CheckRole',
            'tenant.auth' => 'App\\Http\\Middleware\\AuthenticateTenantToken',
            'tenant.user' => EnsureTenantUserAssociation::class,
            'profile.selected' => 'App\\Http\\Middleware\\EnsureProfileSelection',
            'policies.accepted.web' => 'App\\Http\\Middleware\\EnsureWebPoliciesAccepted',
            'exercise.media.host' => RestrictExerciseMediaHost::class,
            'policies.accepted' => 'App\\Http\\Middleware\\EnsurePoliciesAccepted',
            'subscription' => 'App\\Http\\Middleware\\CheckSubscription',
            'system.admin' => 'App\\Http\\Middleware\\EnsureSystemAdmin',
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            IdentifyTenant::class,
            HandleAppearance::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            IdentifyTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
