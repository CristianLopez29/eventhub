<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Repositories;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\ValueObjects\EventId;
use DateTimeImmutable;

interface SearchEventsRepository
{
    public function findById(EventId $eventId): ?Event;

    /** @return Event[] */
    public function searchByDateRange(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array;
}
