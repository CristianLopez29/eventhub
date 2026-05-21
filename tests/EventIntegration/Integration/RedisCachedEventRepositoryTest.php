<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Integration;

use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use App\EventIntegration\Infrastructure\Cache\RedisCachedEventRepository;
use App\Tests\EventIntegration\Builders\EventBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RedisCachedEventRepositoryTest extends KernelTestCase
{
    private EventRepositoryInterface $repository;
    private RedisCachedEventRepository $cachedRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(EventRepositoryInterface::class);
        $this->cachedRepository = self::getContainer()->get(RedisCachedEventRepository::class);

        $this->cleanDatabase();
        $this->cachedRepository->invalidateSearchCache();
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

    public function test_should_cache_find_by_id(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('cache-test-1')
            ->withTitle('Cached Event')
            ->build();

        $this->repository->save($event);

        $first = $this->cachedRepository->findById($event->id());
        $second = $this->cachedRepository->findById($event->id());

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertTrue($first->id()->equals($second->id()));
        self::assertSame('Cached Event', $second->title());
    }

    public function test_should_cache_search_by_date_range(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('cache-test-2')
            ->withTitle('Search Cached Event')
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->build();

        $this->repository->save($event);

        $startsAt = new DateTimeImmutable('2024-06-01 00:00:00');
        $endsAt = new DateTimeImmutable('2024-06-30 23:59:59');

        $first = $this->cachedRepository->searchByDateRange($startsAt, $endsAt);
        $second = $this->cachedRepository->searchByDateRange($startsAt, $endsAt);

        self::assertCount(1, $first);
        self::assertCount(1, $second);
        self::assertSame('Search Cached Event', $second[0]->title());
    }

    public function test_should_invalidate_event_cache_on_save(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('cache-test-3')
            ->withTitle('Original Cache')
            ->build();

        $this->repository->save($event);

        $this->cachedRepository->findById($event->id());

        $updatedEvent = EventBuilder::create()
            ->withProviderId('cache-test-3')
            ->withTitle('Updated Cache')
            ->build();

        $this->cachedRepository->save($updatedEvent);

        $retrieved = $this->cachedRepository->findById($event->id());

        self::assertNotNull($retrieved);
        self::assertSame('Updated Cache', $retrieved->title());
    }

    public function test_should_clear_all_search_cache(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('cache-test-4')
            ->withTitle('Original Title')
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->build();

        $this->repository->save($event);

        $startsAt = new DateTimeImmutable('2024-06-01 00:00:00');
        $endsAt = new DateTimeImmutable('2024-06-30 23:59:59');

        $this->cachedRepository->searchByDateRange($startsAt, $endsAt);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            'UPDATE events SET title = ? WHERE id = ?',
            ['Updated Title', $event->id()->value()]
        );
        $entityManager->clear();

        $this->cachedRepository->invalidateSearchCache();

        $results = $this->cachedRepository->searchByDateRange($startsAt, $endsAt);

        self::assertCount(1, $results);
        self::assertSame('Updated Title', $results[0]->title());
    }
}
