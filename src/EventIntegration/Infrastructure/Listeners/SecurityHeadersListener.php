<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Listeners;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Writes the response-level security headers the app can assert on its own, whatever sits in
 * front of it.
 *
 * In production the shared Traefik proxy also sends HSTS, nosniff and frameDeny; this
 * listener only fills a header the response does not already carry, so the edge value wins
 * where both apply and local development stops being bare. Content-Security-Policy is set
 * here rather than at the edge because it is the one header that has to differ per route.
 *
 * The API answers JSON, so its policy is the strictest there is. The one HTML page it serves
 * — Swagger UI at /api/doc — needs to load its own script and stylesheet, so it gets a
 * policy wide enough for that and nothing else.
 */
final readonly class SecurityHeadersListener
{
    private const string DOCS_UI_PATH = '/api/doc';

    private const string API_CSP = "default-src 'none'; frame-ancestors 'none'";

    private const string DOCS_UI_CSP = "default-src 'none'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "frame-ancestors 'none'";

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        $this->fillIfAbsent($response, 'X-Content-Type-Options', 'nosniff');
        $this->fillIfAbsent($response, 'X-Frame-Options', 'DENY');
        $this->fillIfAbsent($response, 'Content-Security-Policy', $this->cspFor($event));
    }

    private function cspFor(ResponseEvent $event): string
    {
        $path = $event->getRequest()->getPathInfo();
        $isDocsUi = $path === self::DOCS_UI_PATH || str_starts_with($path, self::DOCS_UI_PATH . '/');

        return $isDocsUi ? self::DOCS_UI_CSP : self::API_CSP;
    }

    private function fillIfAbsent(Response $response, string $header, string $value): void
    {
        if (!$response->headers->has($header)) {
            $response->headers->set($header, $value);
        }
    }
}
