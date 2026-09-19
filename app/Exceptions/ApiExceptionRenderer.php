<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Safety net for exceptions that would otherwise reach the client in Laravel's
 * or Symfony's default error format instead of the project's unified
 * {error: {code, message, fields}} shape. Domain exceptions already implement
 * their own render() and are never passed here (Laravel calls those first);
 * this only catches what falls through, e.g. rate limiting, route-model-binding
 * lookups, abort(), and Gate::authorize() failures.
 */
class ApiExceptionRenderer
{
    private const CODES_BY_STATUS = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        422 => 'VALIDATION_ERROR',
        429 => 'TOO_MANY_REQUESTS',
    ];

    private const MESSAGES_BY_STATUS = [
        400 => 'The request could not be understood.',
        401 => 'Authentication is required to access this resource.',
        403 => 'You are not authorized to perform this action.',
        404 => 'The requested resource was not found.',
        405 => 'This method is not allowed for the requested route.',
        409 => 'The request could not be completed due to a conflict.',
        422 => 'The given data was invalid.',
        429 => 'Too many requests. Please try again later.',
    ];

    /**
     * Return a unified-format JSON response for $e, or null to let Laravel's
     * default rendering (or a later registered callback) handle it instead.
     */
    public static function render(Throwable $e): ?JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'fields' => $e->errors(),
                ],
            ], $e->status);
        }

        if ($e instanceof AuthenticationException) {
            return self::response(401);
        }

        if ($e instanceof HttpExceptionInterface) {
            return self::response($e->getStatusCode());
        }

        return null;
    }

    private static function response(int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => self::CODES_BY_STATUS[$status] ?? 'HTTP_ERROR',
                'message' => self::MESSAGES_BY_STATUS[$status] ?? 'An error occurred while processing your request.',
                'fields' => (object) [],
            ],
        ], $status);
    }
}
