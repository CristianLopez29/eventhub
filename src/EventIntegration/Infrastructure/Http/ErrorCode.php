<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-readable codes for failures that no domain exception describes — the ones the
 * framework raises before or around the application (routing, method negotiation, auth).
 */
enum ErrorCode: string
{
    case BAD_REQUEST = 'BAD_REQUEST';
    case AUTHENTICATION_REQUIRED = 'AUTHENTICATION_REQUIRED';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case ACCESS_DENIED = 'ACCESS_DENIED';
    case NOT_FOUND = 'NOT_FOUND';
    case METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    case TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';
    case INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';

    public static function forStatus(int $statusCode): self
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => self::BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED => self::AUTHENTICATION_REQUIRED,
            Response::HTTP_FORBIDDEN => self::ACCESS_DENIED,
            Response::HTTP_NOT_FOUND => self::NOT_FOUND,
            Response::HTTP_METHOD_NOT_ALLOWED => self::METHOD_NOT_ALLOWED,
            Response::HTTP_TOO_MANY_REQUESTS => self::TOO_MANY_REQUESTS,
            default => self::INTERNAL_SERVER_ERROR,
        };
    }
}
