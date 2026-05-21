<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Domain\Entities;

use App\EventIntegration\Domain\Enums\SellMode;
use App\Tests\EventIntegration\Builders\EventBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    #[Test]
    public function should_calculate_min_price_from_zones(): void
    {
        $event = EventBuilder::create()
            ->withZone('General', 20.00, 100)
            ->withZone('VIP', 50.00, 50)
            ->build();

        $this->assertSame(2000, $event->minPrice()?->cents());
    }

    #[Test]
    public function should_calculate_max_price_from_zones(): void
    {
        $event = EventBuilder::create()
            ->withZone('General', 20.00, 100)
            ->withZone('VIP', 50.00, 50)
            ->build();

        $this->assertSame(5000, $event->maxPrice()?->cents());
    }

    #[Test]
    public function should_return_null_prices_when_no_zones(): void
    {
        $event = EventBuilder::create()->build();

        $this->assertNull($event->minPrice());
        $this->assertNull($event->maxPrice());
    }

    #[Test]
    public function should_detect_online_sell_mode(): void
    {
        $event = EventBuilder::create()->withSellMode(SellMode::ONLINE)->build();

        $this->assertTrue($event->isOnline());
    }

    #[Test]
    public function should_detect_offline_sell_mode(): void
    {
        $event = EventBuilder::create()->withSellMode(SellMode::OFFLINE)->build();

        $this->assertFalse($event->isOnline());
    }

    #[Test]
    public function should_detect_overlap_when_event_within_range(): void
    {
        $event = EventBuilder::create()
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->build();

        $this->assertTrue($event->overlapsWith(
            new DateTimeImmutable('2024-06-15 00:00:00'),
            new DateTimeImmutable('2024-06-15 23:59:59')
        ));
    }

    #[Test]
    public function should_detect_overlap_when_range_within_event(): void
    {
        $event = EventBuilder::create()
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 14:00:00'))
            ->build();

        $this->assertTrue($event->overlapsWith(
            new DateTimeImmutable('2024-06-15 11:00:00'),
            new DateTimeImmutable('2024-06-15 13:00:00')
        ));
    }

    #[Test]
    public function should_detect_no_overlap_when_event_before_range(): void
    {
        $event = EventBuilder::create()
            ->withStartsAt(new DateTimeImmutable('2024-06-14 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-14 12:00:00'))
            ->build();

        $this->assertFalse($event->overlapsWith(
            new DateTimeImmutable('2024-06-15 00:00:00'),
            new DateTimeImmutable('2024-06-15 23:59:59')
        ));
    }

    #[Test]
    public function should_detect_no_overlap_when_event_after_range(): void
    {
        $event = EventBuilder::create()
            ->withStartsAt(new DateTimeImmutable('2024-06-16 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-16 12:00:00'))
            ->build();

        $this->assertFalse($event->overlapsWith(
            new DateTimeImmutable('2024-06-15 00:00:00'),
            new DateTimeImmutable('2024-06-15 23:59:59')
        ));
    }

    #[Test]
    public function should_detect_overlap_when_event_starts_at_range_end(): void
    {
        $event = EventBuilder::create()
            ->withStartsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 14:00:00'))
            ->build();

        $this->assertTrue($event->overlapsWith(
            new DateTimeImmutable('2024-06-15 10:00:00'),
            new DateTimeImmutable('2024-06-15 12:00:00')
        ));
    }

    #[Test]
    public function should_detect_overlap_when_event_ends_at_range_start(): void
    {
        $event = EventBuilder::create()
            ->withStartsAt(new DateTimeImmutable('2024-06-15 08:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->build();

        $this->assertTrue($event->overlapsWith(
            new DateTimeImmutable('2024-06-15 10:00:00'),
            new DateTimeImmutable('2024-06-15 12:00:00')
        ));
    }
}
