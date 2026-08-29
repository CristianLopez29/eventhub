<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Listeners;

use App\EventIntegration\Infrastructure\Http\ApiResponse;
use App\EventIntegration\Infrastructure\Http\ErrorCode;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Rewrites every response Lexik produces into the API envelope.
 *
 * The firewall answers before any controller runs, so these responses never pass through
 * ExceptionListener. Without this listener the whole authentication surface — the token
 * itself and all four 401 variants — answers in Lexik's own `{"code", "message"}` shape.
 */
final readonly class JwtAuthenticationListener
{
    #[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $event->setData(['data' => $event->getData(), 'error' => null]);
    }

    /**
     * Login throttling surfaces as an authentication failure too, so it has to be told
     * apart here — otherwise a rate-limited caller is told their credentials are wrong and
     * keeps retrying.
     */
    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        if ($event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            $event->setResponse(ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS->value,
                'Too many failed login attempts. Try again later.',
                Response::HTTP_TOO_MANY_REQUESTS
            ));

            return;
        }

        $event->setResponse(ApiResponse::error(
            ErrorCode::INVALID_CREDENTIALS->value,
            'Invalid credentials.',
            Response::HTTP_UNAUTHORIZED
        ));
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onJwtNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse(ApiResponse::error(
            ErrorCode::AUTHENTICATION_REQUIRED->value,
            'Authentication required. Send a bearer token in the Authorization header.',
            Response::HTTP_UNAUTHORIZED
        ));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse(ApiResponse::error(
            ErrorCode::INVALID_TOKEN->value,
            'The provided token is not valid.',
            Response::HTTP_UNAUTHORIZED
        ));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(ApiResponse::error(
            ErrorCode::TOKEN_EXPIRED->value,
            'The provided token has expired.',
            Response::HTTP_UNAUTHORIZED
        ));
    }
}
