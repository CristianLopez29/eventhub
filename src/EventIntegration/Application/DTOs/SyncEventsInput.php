<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\DTOs;

final readonly class SyncEventsInput
{
    /**
     * @param array<array<string, mixed>> $events
     */
    public function __construct(
        public array $events
    ) {
    }
}
