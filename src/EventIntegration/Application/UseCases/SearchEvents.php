<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\UseCases;

use App\EventIntegration\Application\DTOs\SearchEventsInput;
use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;

final readonly class SearchEvents
{
    public function __construct(
        private EventRepositoryInterface $eventRepository
    ) {
    }

    /** @return Event[] */
    public function search(SearchEventsInput $input): array
    {
        return $this->eventRepository->searchByDateRange(
            $input->startsAt,
            $input->endsAt
        );
    }
}
