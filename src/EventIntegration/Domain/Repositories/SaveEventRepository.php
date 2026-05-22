<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Repositories;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\ValueObjects\EventId;

interface SaveEventRepository
{
    public function exists(EventId $eventId): bool;

    public function save(Event $event): void;
}
