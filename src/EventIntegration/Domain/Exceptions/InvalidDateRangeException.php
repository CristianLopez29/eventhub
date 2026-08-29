<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Exceptions;

use DateTimeImmutable;
use InvalidArgumentException;

final class InvalidDateRangeException extends InvalidArgumentException implements DomainException
{
    private const string ERROR_CODE = 'INVALID_DATE_RANGE';

    public static function endsBeforeItStarts(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): self
    {
        return new self(sprintf(
            'ends_at (%s) must not be earlier than starts_at (%s)',
            $endsAt->format(DATE_ATOM),
            $startsAt->format(DATE_ATOM)
        ));
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }
}
