<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\SentryContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SentryContext::class);
        $middleware->alias(['not-blocked' => EnsureUserIsNotBlocked::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        // Safety net: any exception reaching the API without a domain render()
        // (rate limiting, route-model-binding 404s, abort(), authorization
        // failures, ...) is normalized to the project's {error: {code, message,
        // fields}} format instead of Laravel/Symfony's default error body.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiExceptionRenderer::render($e);
        });
    })->create();
