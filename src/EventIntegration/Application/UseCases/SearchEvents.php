<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\UseCases;

use App\EventIntegration\Application\DTOs\SearchEventsInput;
use App\EventIntegration\Application\DTOs\SearchEventsResult;
use App\EventIntegration\Domain\Repositories\SearchEventsRepository;

final readonly class SearchEvents
{
    public function __construct(
        private SearchEventsRepository $eventRepository
    ) {
    }

    public function search(SearchEventsInput $searchInput): SearchEventsResult
    {
        $total = $this->eventRepository->countByDateRange($searchInput->startsAt, $searchInput->endsAt);

        // A page past the end has nothing to fetch, so the range query is skipped entirely.
        if ($searchInput->offset() >= $total) {
            return new SearchEventsResult([], $searchInput->page, $searchInput->perPage, $total);
        }

        $events = $this->eventRepository->searchByDateRange(
            $searchInput->startsAt,
            $searchInput->endsAt,
            $searchInput->perPage,
            $searchInput->offset()
        );

        return new SearchEventsResult($events, $searchInput->page, $searchInput->perPage, $total);
    }
}
