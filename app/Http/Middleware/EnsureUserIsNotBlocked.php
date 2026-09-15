<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Exceptions\AccountBlockedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBlocked
{
    /**
     * Reject requests from users blocked after their token was issued.
     * isBlocked() is otherwise only checked at login/register, so a token
     * created before the block was placed would keep working indefinitely.
     *
     * @throws AccountBlockedException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            throw new AccountBlockedException();
        }

        return $next($request);
    }
}
