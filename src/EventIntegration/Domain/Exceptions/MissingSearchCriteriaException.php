<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Exceptions;

use InvalidArgumentException;

final class MissingSearchCriteriaException extends InvalidArgumentException implements DomainException
{
    private const string ERROR_CODE = 'INVALID_PARAMETERS';

    /** @param list<string> $fields */
    public static function forFields(array $fields): self
    {
        return new self(sprintf('Missing required query parameters: %s', implode(' and ', $fields)));
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
