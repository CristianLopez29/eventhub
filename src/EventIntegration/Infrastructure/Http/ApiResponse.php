<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single place the `{"data": ..., "error": ...}` envelope is built.
 *
 * Every response the API emits goes through here — including the ones the security layer
 * produces before a controller runs — so the shape cannot drift between success, domain
 * failure and authentication failure paths.
 */
final readonly class ApiResponse
{
    public static function success(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'error' => null], $status);
    }

    public static function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(
            ['data' => null, 'error' => ['code' => $code, 'message' => $message]],
            $status
        );
    }
}
