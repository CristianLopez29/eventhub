<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Repositories;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\ValueObjects\EventId;
use DateTimeImmutable;

interface EventRepositoryInterface
{
    public function findById(EventId $eventId): ?Event;

    public function exists(EventId $eventId): bool;

    /** @return Event[] */
    public function searchByDateRange(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array;

    public function save(Event $event): void;
}
