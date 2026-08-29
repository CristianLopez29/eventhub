<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Controllers;

use App\EventIntegration\Infrastructure\Health\DependencyProbe;
use App\EventIntegration\Infrastructure\Http\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Readiness probe: reports whether the dependencies this app cannot serve traffic without
 * are reachable. 503 when either is down, so an uptime monitor can alert on it.
 */
final readonly class ReadinessController
{
    private const string TOKEN_HEADER = 'X-Health-Check-Token';

    public function __construct(
        private DependencyProbe $probe,
        private string $healthCheckToken,
    ) {
    }

    #[OA\Get(
        path: '/health/ready',
        summary: 'Readiness probe (database and cache)',
        tags: ['Health'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'X-Health-Check-Token',
                in: 'header',
                required: false,
                description: 'Required whenever HEALTHCHECK_TOKEN is configured',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Every dependency is reachable'),
            new OA\Response(response: 403, description: 'Missing or wrong health-check token'),
            new OA\Response(response: 503, description: 'At least one dependency is unreachable'),
        ]
    )]
    #[Route('/health/ready', name: 'app_health_ready', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isAuthorised($request)) {
            return ApiResponse::error(
                'FORBIDDEN',
                'A valid ' . self::TOKEN_HEADER . ' header is required.',
                Response::HTTP_FORBIDDEN
            );
        }

        $checks = $this->probe->run();
        $isReady = !in_array(false, $checks, true);

        return ApiResponse::success(
            ['status' => $isReady ? 'ready' : 'degraded', 'checks' => $checks],
            $isReady ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    /**
     * An unset token leaves the probe open, which is what local development wants; the
     * production environment file sets one and the probe closes behind it.
     */
    private function isAuthorised(Request $request): bool
    {
        if ($this->healthCheckToken === '') {
            return true;
        }

        $providedToken = $request->headers->get(self::TOKEN_HEADER, '');

        return is_string($providedToken) && hash_equals($this->healthCheckToken, $providedToken);
    }
}
