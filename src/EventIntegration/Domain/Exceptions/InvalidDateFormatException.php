<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidDateFormatException extends InvalidArgumentException
{
    public static function forField(string $field, string $value, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Invalid date format for field "%s": "%s"', $field, $value), 0, $previous);
    }
}
