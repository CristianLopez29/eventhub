<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\DTOs;

use App\EventIntegration\Domain\Entities\Event;

final readonly class SyncEventsInput
{
    /** @param Event[] $events */
    public function __construct(
        public array $events
    ) {
    }
}
