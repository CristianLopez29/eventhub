<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Price
{
    public function __construct(
        private int $cents
    ) {
        if ($this->cents < 0) {
            throw new InvalidArgumentException('Price cannot be negative');
        }
    }

    public static function fromFloat(float $amount): self
    {
        return new self((int) round($amount * 100));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function toFloat(): float
    {
        return $this->cents / 100.0;
    }

    public function lessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function greaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }
}
