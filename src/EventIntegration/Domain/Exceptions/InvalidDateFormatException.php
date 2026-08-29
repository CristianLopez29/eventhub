<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Exceptions;

use InvalidArgumentException;
use Throwable;

final class InvalidDateFormatException extends InvalidArgumentException implements DomainException
{
    private const string ERROR_CODE = 'INVALID_DATE_FORMAT';

    public static function forField(string $field, string $value, ?Throwable $previous = null): self
    {
        return new self(sprintf('Invalid date format for field "%s": "%s". Expected: YYYY-MM-DDTHH:mm:ss', $field, $value), 0, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
