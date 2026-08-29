<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Listeners;

use App\EventIntegration\Infrastructure\Http\ApiResponse;
use App\EventIntegration\Infrastructure\Http\ErrorCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Guards the Swagger UI and its raw OpenAPI document with HTTP Basic auth outside local
 * development.
 *
 * The JWT firewall cannot protect these routes: a browser opening /api/doc has no way to
 * attach a bearer token, so every visit would just 401. Basic auth is the one scheme a
 * browser satisfies on its own. Left open under APP_ENV dev and test so the local stack and
 * the acceptance suite reach the docs without credentials.
 */
final readonly class DocsAccessListener
{
    private const string PATH_PREFIX = '/api/doc';
    private const string REALM = 'EventHub API documentation';

    /**
     * @param list<string> $unprotectedEnvironments
     */
    public function __construct(
        private string $username,
        private string $password,
        private string $environment,
        private array $unprotectedEnvironments = ['dev', 'test'],
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!str_starts_with($event->getRequest()->getPathInfo(), self::PATH_PREFIX)) {
            return;
        }

        if (in_array($this->environment, $this->unprotectedEnvironments, true)) {
            return;
        }

        // Fail closed: a blank password must never be read as "open to everyone".
        if ($this->password === '') {
            $event->setResponse(ApiResponse::error(
                'DOCUMENTATION_UNAVAILABLE',
                'API documentation is not available.',
                Response::HTTP_SERVICE_UNAVAILABLE,
            ));

            return;
        }

        if (!$this->credentialsMatch($event->getRequest())) {
            $response = ApiResponse::error(
                ErrorCode::AUTHENTICATION_REQUIRED->value,
                'Valid credentials are required to read the API documentation.',
                Response::HTTP_UNAUTHORIZED,
            );
            $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', self::REALM));

            $event->setResponse($response);
        }
    }

    private function credentialsMatch(Request $request): bool
    {
        // Both comparisons run unconditionally so the time taken never reveals which half
        // of the pair was wrong.
        $usernameMatches = hash_equals($this->username, (string) $request->getUser());
        $passwordMatches = hash_equals($this->password, (string) $request->getPassword());

        return $usernameMatches && $passwordMatches;
    }
}
