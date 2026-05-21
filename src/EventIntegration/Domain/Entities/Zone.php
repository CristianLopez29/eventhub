<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Entities;

use App\EventIntegration\Domain\ValueObjects\Price;
use App\EventIntegration\Domain\ValueObjects\ZoneName;

final readonly class Zone
{
    public function __construct(
        private ZoneName $name,
        private Price $price,
        private int $capacity
    ) {
    }

    public function name(): ZoneName
    {
        return $this->name;
    }

    public function price(): Price
    {
        return $this->price;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }
}
