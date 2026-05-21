<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class ZoneName
{
    public function __construct(
        private string $value
    ) {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException('Zone name cannot be empty');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
