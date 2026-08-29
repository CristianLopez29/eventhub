<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Application\UseCases;

use App\EventIntegration\Application\DTOs\SearchEventsInput;
use App\EventIntegration\Application\UseCases\SearchEvents;
use App\EventIntegration\Domain\Repositories\SearchEventsRepository;
use App\Tests\EventIntegration\Builders\EventBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SearchEventsTest extends TestCase
{
    private SearchEventsRepository&MockObject $eventRepository;
    private SearchEvents $useCase;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createMock(SearchEventsRepository::class);
        $this->useCase = new SearchEvents($this->eventRepository);
    }

    public function test_should_return_events_within_date_range(): void
    {
        $startsAt = new DateTimeImmutable('2024-06-01 00:00:00');
        $endsAt = new DateTimeImmutable('2024-06-30 23:59:59');
        $input = new SearchEventsInput($startsAt, $endsAt);

        $expectedEvents = [
            EventBuilder::create()->withTitle('Event A')->build(),
            EventBuilder::create()->withTitle('Event B')->build(),
        ];

        $this->eventRepository
            ->expects($this->once())
            ->method('countByDateRange')
            ->with($startsAt, $endsAt)
            ->willReturn(2);

        $this->eventRepository
            ->expects($this->once())
            ->method('searchByDateRange')
            ->with($startsAt, $endsAt, SearchEventsInput::DEFAULT_PER_PAGE, 0)
            ->willReturn($expectedEvents);

        $searchResult = $this->useCase->search($input);

        $this->assertSame($expectedEvents, $searchResult->events);
        $this->assertSame(2, $searchResult->total);
        $this->assertSame(1, $searchResult->totalPages());
    }

    public function test_should_return_empty_array_when_no_events_found(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-01 00:00:00');
        $endsAt = new DateTimeImmutable('2024-01-31 23:59:59');
        $input = new SearchEventsInput($startsAt, $endsAt);

        $this->eventRepository
            ->expects($this->once())
            ->method('countByDateRange')
            ->willReturn(0);

        $this->eventRepository
            ->expects($this->never())
            ->method('searchByDateRange');

        $searchResult = $this->useCase->search($input);

        $this->assertSame([], $searchResult->events);
        $this->assertSame(0, $searchResult->total);
    }

    public function test_should_delegate_exact_dates_to_repository(): void
    {
        $startsAt = new DateTimeImmutable('2024-12-25 00:00:00');
        $endsAt = new DateTimeImmutable('2024-12-25 23:59:59');
        $input = new SearchEventsInput($startsAt, $endsAt);

        $this->eventRepository
            ->expects($this->once())
            ->method('countByDateRange')
            ->with(
                $this->callback(fn (DateTimeImmutable $rangeStart): bool => $rangeStart->format('Y-m-d H:i:s') === '2024-12-25 00:00:00'),
                $this->callback(fn (DateTimeImmutable $rangeEnd): bool => $rangeEnd->format('Y-m-d H:i:s') === '2024-12-25 23:59:59')
            )
            ->willReturn(0);

        $this->useCase->search($input);
    }
}
