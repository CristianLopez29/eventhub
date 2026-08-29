<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\DTOs;

use App\EventIntegration\Domain\Entities\Event;

final readonly class SearchEventsResult
{
    /** @param Event[] $events */
    public function __construct(
        public array $events,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }

    public function totalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }
}
