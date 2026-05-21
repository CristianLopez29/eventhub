<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Entities;

use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\ValueObjects\EventId;
use App\EventIntegration\Domain\ValueObjects\Price;
use DateTimeImmutable;

final class Event
{
    /** @var Zone[] */
    private array $zones = [];

    public function __construct(
        private readonly EventId $id,
        private readonly string $title,
        private readonly DateTimeImmutable $startsAt,
        private readonly DateTimeImmutable $endsAt,
        private readonly SellMode $sellMode
    ) {
    }

    public function id(): EventId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function sellMode(): SellMode
    {
        return $this->sellMode;
    }

    public function addZone(Zone $zone): void
    {
        $this->zones[] = $zone;
    }

    /** @return Zone[] */
    public function zones(): array
    {
        return $this->zones;
    }

    public function minPrice(): ?Price
    {
        if ($this->zones === []) {
            return null;
        }

        $minPrice = $this->zones[0]->price();

        foreach ($this->zones as $zone) {
            if ($zone->price()->lessThan($minPrice)) {
                $minPrice = $zone->price();
            }
        }

        return $minPrice;
    }

    public function maxPrice(): ?Price
    {
        if ($this->zones === []) {
            return null;
        }

        $maxPrice = $this->zones[0]->price();

        foreach ($this->zones as $zone) {
            if ($zone->price()->greaterThan($maxPrice)) {
                $maxPrice = $zone->price();
            }
        }

        return $maxPrice;
    }

    public function isOnline(): bool
    {
        return $this->sellMode === SellMode::ONLINE;
    }

    public function overlapsWith(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $this->startsAt <= $to && $this->endsAt >= $from;
    }
}
