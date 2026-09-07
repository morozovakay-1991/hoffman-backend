<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class SentryContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Integration::configureScope(function (Scope $scope) use ($request): void {
            $scope->setUser([
                'id' => $request->user()?->getAuthIdentifier(),
            ]);

            $scope->setTag('route', $request->route()?->getName() ?? $request->path());
        });

        return $next($request);
    }
}
