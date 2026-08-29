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
        return $this->foldPrices(static fn (Price $cheapest, Price $candidate): Price => $candidate->lessThan($cheapest) ? $candidate : $cheapest);
    }

    public function maxPrice(): ?Price
    {
        return $this->foldPrices(static fn (Price $dearest, Price $candidate): Price => $candidate->greaterThan($dearest) ? $candidate : $dearest);
    }

    /** @param callable(Price, Price): Price $pick */
    private function foldPrices(callable $pick): ?Price
    {
        if ($this->zones === []) {
            return null;
        }

        $prices = array_map(static fn (Zone $zone): Price => $zone->price(), $this->zones);

        return array_reduce($prices, $pick, $prices[0]);
    }

    public function isOnline(): bool
    {
        return $this->sellMode === SellMode::ONLINE;
    }
}
