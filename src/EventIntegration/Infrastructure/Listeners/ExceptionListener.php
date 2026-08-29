<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Listeners;

use App\EventIntegration\Domain\Exceptions\DomainException;
use App\EventIntegration\Infrastructure\Http\ApiResponse;
use App\EventIntegration\Infrastructure\Http\ErrorCode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ExceptionListener
{
    private const string INTERNAL_ERROR_MESSAGE = 'Internal server error';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = $this->resolveStatusCode($exception);

        $this->log($exception, $statusCode);

        $event->setResponse(ApiResponse::error(
            $this->resolveErrorCode($exception, $statusCode),
            $this->resolveMessage($exception, $statusCode),
            $statusCode
        ));
    }

    private function resolveStatusCode(Throwable $exception): int
    {
        if ($exception instanceof DomainException) {
            return Response::HTTP_BAD_REQUEST;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function resolveErrorCode(Throwable $exception, int $statusCode): string
    {
        if ($exception instanceof DomainException) {
            return $exception->errorCode();
        }

        return ErrorCode::forStatus($statusCode)->value;
    }

    /**
     * A 5xx message can carry internals (SQL, file paths), so it never reaches the client;
     * a 4xx message is the explanation the caller needs to fix their request.
     */
    private function resolveMessage(Throwable $exception, int $statusCode): string
    {
        if ($statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            return self::INTERNAL_ERROR_MESSAGE;
        }

        return $exception->getMessage();
    }

    private function log(Throwable $exception, int $statusCode): void
    {
        if ($statusCode < Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->logger->info('API client error', [
                'message' => $exception->getMessage(),
                'status_code' => $statusCode,
            ]);

            return;
        }

        $this->logger->error('API exception occurred', [
            'message' => $exception->getMessage(),
            'status_code' => $statusCode,
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
