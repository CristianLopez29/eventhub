<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Events;

use App\EventIntegration\Domain\ValueObjects\EventId;
use DateTimeImmutable;

final readonly class EventSynchronized
{
    public function __construct(
        private EventId $eventId,
        private DateTimeImmutable $occurredAt
    ) {
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
