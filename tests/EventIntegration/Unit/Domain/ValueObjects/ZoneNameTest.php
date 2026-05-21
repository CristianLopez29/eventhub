<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Domain\ValueObjects;

use App\EventIntegration\Domain\ValueObjects\ZoneName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZoneNameTest extends TestCase
{
    #[Test]
    public function should_create_valid_zone_name(): void
    {
        $zoneName = new ZoneName('General Admission');

        $this->assertSame('General Admission', $zoneName->value());
    }

    #[Test]
    public function should_reject_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ZoneName('');
    }

    #[Test]
    public function should_reject_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ZoneName('   ');
    }

    #[Test]
    public function should_detect_equality(): void
    {
        $zoneName1 = new ZoneName('VIP');
        $zoneName2 = new ZoneName('VIP');
        $zoneName3 = new ZoneName('General');

        $this->assertTrue($zoneName1->equals($zoneName2));
        $this->assertFalse($zoneName1->equals($zoneName3));
    }
}
