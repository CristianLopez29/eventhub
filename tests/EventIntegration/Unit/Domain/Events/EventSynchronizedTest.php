<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Domain\Events;

use App\EventIntegration\Domain\Events\EventSynchronized;
use App\EventIntegration\Domain\ValueObjects\EventId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventSynchronizedTest extends TestCase
{
    #[Test]
    public function should_capture_event_id_and_timestamp(): void
    {
        $eventId = EventId::fromProviderId('provider-123');
        $occurredAt = new DateTimeImmutable('2024-06-15 12:00:00');

        $event = new EventSynchronized($eventId, $occurredAt);

        $this->assertTrue($event->eventId()->equals($eventId));
        $this->assertSame($occurredAt, $event->occurredAt());
    }
}
