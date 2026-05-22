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
    public function __construct(
        private SaveEventRepository $eventRepository,
        private LoggerInterface $logger,
        private EventCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function sync(SyncEventsInput $input): SyncResult
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($input->events as $event) {
            if (!$event->isOnline()) {
                $this->logger->info('Skipping non-online event', ['event_id' => $event->id()->value()]);
                ++$skipped;

                continue;
            }

            if ($this->eventRepository->exists($event->id())) {
                ++$updated;
                $this->logger->info('Updating existing event', ['event_id' => $event->id()->value()]);
            } else {
                ++$inserted;
                $this->logger->info('Inserting new event', ['event_id' => $event->id()->value()]);
            }

            $this->eventRepository->save($event);
        }

        $this->cacheInvalidator->invalidateSearchCache();

        return new SyncResult($inserted, $updated, $skipped);
    }
}
