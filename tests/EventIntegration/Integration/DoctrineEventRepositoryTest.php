<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Integration;

use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use App\EventIntegration\Domain\ValueObjects\EventId;
use App\Tests\EventIntegration\Builders\EventBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineEventRepositoryTest extends KernelTestCase
{
    private EventRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(EventRepositoryInterface::class);

        $this->cleanDatabase();
    }

    private function cleanDatabase(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $entityManager->getConnection();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE zones');
        $connection->executeStatement('TRUNCATE TABLE events');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_should_save_and_retrieve_event(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('provider-123')
            ->withTitle('Test Event')
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->withZone('General', 50.0, 100)
            ->withZone('VIP', 100.0, 50)
            ->build();

        $this->repository->save($event);

        $retrieved = $this->repository->findById($event->id());

        self::assertNotNull($retrieved);
        self::assertTrue($event->id()->equals($retrieved->id()));
        self::assertSame('Test Event', $retrieved->title());
        self::assertCount(2, $retrieved->zones());
    }

    public function test_should_return_null_when_event_not_found(): void
    {
        $result = $this->repository->findById(EventId::fromProviderId('non-existent'));

        self::assertNull($result);
    }

    public function test_should_update_existing_event(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('provider-456')
            ->withTitle('Original Title')
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->withZone('General', 50.0, 100)
            ->build();

        $this->repository->save($event);

        $updatedEvent = EventBuilder::create()
            ->withProviderId('provider-456')
            ->withTitle('Updated Title')
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->withZone('General', 60.0, 150)
            ->withZone('Premium', 120.0, 30)
            ->build();

        $this->repository->save($updatedEvent);

        $retrieved = $this->repository->findById($updatedEvent->id());

        self::assertNotNull($retrieved);
        self::assertSame('Updated Title', $retrieved->title());
        self::assertCount(2, $retrieved->zones());
    }

    public function test_should_search_events_by_date_range(): void
    {
        $event1 = EventBuilder::create()
            ->withProviderId('event-1')
            ->withTitle('June Event')
            ->withStartsAt(new DateTimeImmutable('2024-06-10 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-10 12:00:00'))
            ->build();

        $event2 = EventBuilder::create()
            ->withProviderId('event-2')
            ->withTitle('July Event')
            ->withStartsAt(new DateTimeImmutable('2024-07-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-07-15 12:00:00'))
            ->build();

        $event3 = EventBuilder::create()
            ->withProviderId('event-3')
            ->withTitle('August Event')
            ->withStartsAt(new DateTimeImmutable('2024-08-20 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-08-20 12:00:00'))
            ->build();

        $this->repository->save($event1);
        $this->repository->save($event2);
        $this->repository->save($event3);

        $results = $this->repository->searchByDateRange(
            new DateTimeImmutable('2024-06-01 00:00:00'),
            new DateTimeImmutable('2024-07-31 23:59:59')
        );

        self::assertCount(2, $results);
        self::assertSame('June Event', $results[0]->title());
        self::assertSame('July Event', $results[1]->title());
    }

    public function test_should_return_empty_array_when_no_events_in_range(): void
    {
        $results = $this->repository->searchByDateRange(
            new DateTimeImmutable('2025-01-01 00:00:00'),
            new DateTimeImmutable('2025-12-31 23:59:59')
        );

        self::assertSame([], $results);
    }

    public function test_should_preserve_past_events_not_in_provider_response(): void
    {
        $pastEvent = EventBuilder::create()
            ->withProviderId('past-event')
            ->withTitle('Past Event')
            ->withStartsAt(new DateTimeImmutable('2023-01-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2023-01-15 12:00:00'))
            ->build();

        $this->repository->save($pastEvent);

        $results = $this->repository->searchByDateRange(
            new DateTimeImmutable('2023-01-01 00:00:00'),
            new DateTimeImmutable('2023-01-31 23:59:59')
        );

        self::assertCount(1, $results);
        self::assertSame('Past Event', $results[0]->title());
    }
}
