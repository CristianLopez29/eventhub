<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Controllers;

use App\EventIntegration\Infrastructure\Http\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness probe: answers as long as PHP can serve a request.
 *
 * Deliberately touches no dependency. An orchestrator restarts a container that fails
 * liveness, and restarting the app cannot fix a database that is down — that is what the
 * readiness probe is for.
 */
final readonly class HealthController
{
    #[OA\Get(
        path: '/health',
        summary: 'Liveness probe',
        tags: ['Health'],
        security: [],
        responses: [new OA\Response(response: 200, description: 'The process is alive')]
    )]
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(['status' => 'ok']);
    }
}
