<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Domain\ValueObjects;

use App\EventIntegration\Domain\ValueObjects\EventId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventIdTest extends TestCase
{
    #[Test]
    public function should_create_from_valid_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $eventId = new EventId($uuid);

        $this->assertSame($uuid, $eventId->value());
    }

    #[Test]
    public function should_reject_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventId('not-a-uuid');
    }

    #[Test]
    public function should_generate_deterministic_uuid_from_provider_id(): void
    {
        $providerId = 'provider-123';
        $eventId1 = EventId::fromProviderId($providerId);
        $eventId2 = EventId::fromProviderId($providerId);

        $this->assertTrue($eventId1->equals($eventId2));
    }

    #[Test]
    public function should_generate_different_uuid_for_different_provider_ids(): void
    {
        $eventId1 = EventId::fromProviderId('provider-1');
        $eventId2 = EventId::fromProviderId('provider-2');

        $this->assertFalse($eventId1->equals($eventId2));
    }
}
