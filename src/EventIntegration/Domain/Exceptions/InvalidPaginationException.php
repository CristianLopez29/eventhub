<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidPaginationException extends InvalidArgumentException implements DomainException
{
    private const string ERROR_CODE = 'INVALID_PAGINATION';

    public static function forPage(int $page): self
    {
        return new self(sprintf('page must be 1 or greater, got %d', $page));
    }

    public static function forPerPage(int $perPage, int $maximum): self
    {
        return new self(sprintf('per_page must be between 1 and %d, got %d', $maximum, $perPage));
    }

    public static function forNonNumeric(string $field): self
    {
        return new self(sprintf('%s must be a positive integer', $field));
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
