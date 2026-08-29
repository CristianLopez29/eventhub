<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Domain\ValueObjects;

use App\EventIntegration\Domain\ValueObjects\Price;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PriceTest extends TestCase
{
    public function test_should_create_from_cents(): void
    {
        $price = new Price(1500);

        $this->assertSame(1500, $price->cents());
    }

    public function test_should_reject_negative_price(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Price(-1);
    }

    public function test_should_create_from_float(): void
    {
        $price = Price::fromFloat(15.50);

        $this->assertSame(1550, $price->cents());
    }

    public function test_should_convert_to_float(): void
    {
        $price = new Price(1234);

        $this->assertSame(12.34, $price->toFloat());
    }

    public function test_should_compare_less_than(): void
    {
        $lower = new Price(100);
        $higher = new Price(200);

        $this->assertTrue($lower->lessThan($higher));
        $this->assertFalse($higher->lessThan($lower));
    }

    public function test_should_compare_greater_than(): void
    {
        $lower = new Price(100);
        $higher = new Price(200);

        $this->assertTrue($higher->greaterThan($lower));
        $this->assertFalse($lower->greaterThan($higher));
    }
}
