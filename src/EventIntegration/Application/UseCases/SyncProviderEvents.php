<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\UseCases;

use App\EventIntegration\Application\Contracts\EventCacheInvalidator;
use App\EventIntegration\Application\DTOs\SyncEventsInput;
use App\EventIntegration\Application\DTOs\SyncResult;
use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Repositories\SaveEventRepository;
use Psr\Log\LoggerInterface;

final readonly class SyncProviderEvents
{
    private const string OUTCOME_INSERTED = 'inserted';
    private const string OUTCOME_UPDATED = 'updated';
    private const string OUTCOME_SKIPPED = 'skipped';

    public function __construct(
        private SaveEventRepository $eventRepository,
        private LoggerInterface $logger,
        private EventCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function sync(SyncEventsInput $syncInput): SyncResult
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($syncInput->events as $event) {
            $outcome = $this->persist($event);

            $inserted += (int) ($outcome === self::OUTCOME_INSERTED);
            $updated += (int) ($outcome === self::OUTCOME_UPDATED);
            $skipped += (int) ($outcome === self::OUTCOME_SKIPPED);
        }

        // debt: the whole cache namespace is cleared once per sync rather than only the
        // date ranges the synced events fall into. Revisit if syncs run more than once a
        // minute, when the repeated cold reads would start to cost more than the scan.
        $this->cacheInvalidator->invalidateSearchCache();

        return new SyncResult($inserted, $updated, $skipped);
    }

    private function persist(Event $event): string
    {
        if (!$event->isOnline()) {
            $this->logger->info('Skipping non-online event', ['event_id' => $event->id()->value()]);

            return self::OUTCOME_SKIPPED;
        }

        $outcome = $this->eventRepository->exists($event->id())
            ? self::OUTCOME_UPDATED
            : self::OUTCOME_INSERTED;

        $this->logger->info(
            $outcome === self::OUTCOME_UPDATED ? 'Updating existing event' : 'Inserting new event',
            ['event_id' => $event->id()->value()]
        );

        $this->eventRepository->save($event);

        return $outcome;
    }
}
