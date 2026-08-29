<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Exceptions;

use Throwable;

/**
 * A failure the caller caused and can correct.
 *
 * Carries the machine-readable code the API contract exposes, but deliberately not an
 * HTTP status: mapping a domain failure onto a status code is an Infrastructure decision
 * and lives in ExceptionListener.
 */
interface DomainException extends Throwable
{
    public function errorCode(): string;
}
