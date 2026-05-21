<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Cache;

use App\EventIntegration\Application\Contracts\EventCacheInvalidator;
use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use App\EventIntegration\Domain\ValueObjects\EventId;
use DateTimeImmutable;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class RedisCachedEventRepository implements EventRepositoryInterface, EventCacheInvalidator
{
    private CacheInterface $cache;

    public function __construct(
        private EventRepositoryInterface $innerRepository,
        private RedisAdapter $cacheAdapter,
    ) {
        $this->cache = $cacheAdapter;
    }

    public function findById(EventId $eventId): ?Event
    {
        $cacheKey = 'event_' . $eventId->value();

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($eventId): ?Event {
            $item->expiresAfter(3600);

            return $this->innerRepository->findById($eventId);
        });
    }

    public function exists(EventId $eventId): bool
    {
        return $this->innerRepository->exists($eventId);
    }

    public function searchByDateRange(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array
    {
        $cacheKey = sprintf(
            'events_search_%s_%s',
            $startsAt->format('Ymd'),
            $endsAt->format('Ymd')
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($startsAt, $endsAt): array {
            $item->expiresAfter(300);

            return $this->innerRepository->searchByDateRange($startsAt, $endsAt);
        });
    }

    public function save(Event $event): void
    {
        $this->innerRepository->save($event);
        $this->invalidateEventCache($event->id()->value());
        $this->invalidateSearchCache();
    }

    public function invalidateSearchCache(): void
    {
        $this->cacheAdapter->clear();
    }

    private function invalidateEventCache(string $eventId): void
    {
        $this->cache->deleteItem('event_' . $eventId);
    }
}
