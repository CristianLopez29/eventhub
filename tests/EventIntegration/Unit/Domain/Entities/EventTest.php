<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Domain\Entities;

use App\EventIntegration\Domain\Enums\SellMode;
use App\Tests\EventIntegration\Builders\EventBuilder;
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
}
