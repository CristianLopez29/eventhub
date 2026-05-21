<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Application\UseCases;

use App\EventIntegration\Application\DTOs\SearchEventsInput;
use App\EventIntegration\Application\UseCases\SearchEvents;
use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use App\Tests\EventIntegration\Builders\EventBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SearchEventsTest extends TestCase
{
    private EventRepositoryInterface&MockObject $eventRepository;
    private SearchEvents $useCase;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createMock(EventRepositoryInterface::class);
        $this->useCase = new SearchEvents($this->eventRepository);
    }

    #[Test]
    public function should_return_events_within_date_range(): void
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
            ->method('searchByDateRange')
            ->with($startsAt, $endsAt)
            ->willReturn($expectedEvents);

        $result = $this->useCase->search($input);

        $this->assertSame($expectedEvents, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function should_return_empty_array_when_no_events_found(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-01 00:00:00');
        $endsAt = new DateTimeImmutable('2024-01-31 23:59:59');
        $input = new SearchEventsInput($startsAt, $endsAt);

        $this->eventRepository
            ->expects($this->once())
            ->method('searchByDateRange')
            ->with($startsAt, $endsAt)
            ->willReturn([]);

        $result = $this->useCase->search($input);

        $this->assertSame([], $result);
    }

    #[Test]
    public function should_delegate_exact_dates_to_repository(): void
    {
        $startsAt = new DateTimeImmutable('2024-12-25 00:00:00');
        $endsAt = new DateTimeImmutable('2024-12-25 23:59:59');
        $input = new SearchEventsInput($startsAt, $endsAt);

        $this->eventRepository
            ->expects($this->once())
            ->method('searchByDateRange')
            ->with(
                $this->callback(fn (DateTimeImmutable $dt): bool => $dt->format('Y-m-d H:i:s') === '2024-12-25 00:00:00'),
                $this->callback(fn (DateTimeImmutable $dt): bool => $dt->format('Y-m-d H:i:s') === '2024-12-25 23:59:59')
            )
            ->willReturn([]);

        $this->useCase->search($input);
    }
}
