<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Listeners;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

final class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $statusCode = $this->resolveStatusCode($exception);

        $this->logger->error('API exception occurred', [
            'message' => $exception->getMessage(),
            'status_code' => $statusCode,
            'trace' => $exception->getTraceAsString(),
        ]);

        $response = new JsonResponse(
            [
                'error' => [
                    'code' => 'INTERNAL_SERVER_ERROR',
                    'message' => $statusCode >= 500 ? 'Internal server error' : $exception->getMessage(),
                ],
                'data' => null,
            ],
            $statusCode
        );

        $event->setResponse($response);
    }

    private function resolveStatusCode(\Throwable $exception): int
    {
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
