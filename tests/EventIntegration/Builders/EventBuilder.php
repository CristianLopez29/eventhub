<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Builders;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Entities\Zone;
use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\ValueObjects\EventId;
use App\EventIntegration\Domain\ValueObjects\Price;
use App\EventIntegration\Domain\ValueObjects\ZoneName;
use DateTimeImmutable;

final class EventBuilder
{
    private string $providerId = 'default-provider-id';
    private string $title = 'Default Event';
    private DateTimeImmutable $startsAt;
    private DateTimeImmutable $endsAt;
    private SellMode $sellMode = SellMode::ONLINE;

    /** @var array<array{0: string, 1: float, 2: int}> */
    private array $zones = [];

    public function __construct()
    {
        $this->startsAt = new DateTimeImmutable('2024-06-15 10:00:00');
        $this->endsAt = new DateTimeImmutable('2024-06-15 12:00:00');
    }

    public static function create(): self
    {
        return new self();
    }

    public function withProviderId(string $providerId): self
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function withStartsAt(DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function withEndsAt(DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function withSellMode(SellMode $sellMode): self
    {
        $this->sellMode = $sellMode;

        return $this;
    }

    public function withZone(string $name, float $price, int $capacity): self
    {
        $this->zones[] = [$name, $price, $capacity];

        return $this;
    }

    public function build(): Event
    {
        $event = new Event(
            EventId::fromProviderId($this->providerId),
            $this->title,
            $this->startsAt,
            $this->endsAt,
            $this->sellMode
        );

        foreach ($this->zones as [$name, $price, $capacity]) {
            $event->addZone(new Zone(
                new ZoneName($name),
                Price::fromFloat($price),
                $capacity
            ));
        }

        return $event;
    }
}
