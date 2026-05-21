<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\UseCases;

use App\EventIntegration\Application\DTOs\SyncEventsInput;
use App\EventIntegration\Application\DTOs\SyncResult;
use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Entities\Zone;
use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\Exceptions\InvalidDateFormatException;
use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use App\EventIntegration\Domain\ValueObjects\EventId;
use App\EventIntegration\Domain\ValueObjects\Price;
use App\EventIntegration\Domain\ValueObjects\ZoneName;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

final readonly class SyncProviderEvents
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function sync(SyncEventsInput $input): SyncResult
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($input->events as $eventData) {
            Assert::isArray($eventData);

            $sellModeValue = is_string($eventData['sell_mode'] ?? null) ? $eventData['sell_mode'] : '';
            $sellMode = SellMode::tryFrom($sellModeValue);

            if ($sellMode !== SellMode::ONLINE) {
                $this->logger->info('Skipping non-online event', ['sell_mode' => $sellModeValue]);
                ++$skipped;

                continue;
            }

            $event = $this->buildEvent($eventData);

            if ($this->eventRepository->exists($event->id())) {
                ++$updated;
                $this->logger->info('Updating existing event', ['event_id' => $event->id()->value()]);
            } else {
                ++$inserted;
                $this->logger->info('Inserting new event', ['event_id' => $event->id()->value()]);
            }

            $this->eventRepository->save($event);
        }

        return new SyncResult($inserted, $updated, $skipped);
    }

    /** @param array<string, mixed> $eventData */
    private function buildEvent(array $eventData): Event
    {
        Assert::keyExists($eventData, 'base_event_id');
        Assert::keyExists($eventData, 'title');
        Assert::keyExists($eventData, 'start_date');
        Assert::keyExists($eventData, 'end_date');

        $baseEventId = is_scalar($eventData['base_event_id']) ? (string) $eventData['base_event_id'] : '';
        $title = is_scalar($eventData['title']) ? (string) $eventData['title'] : '';
        $startDate = is_scalar($eventData['start_date']) ? (string) $eventData['start_date'] : '';
        $endDate = is_scalar($eventData['end_date']) ? (string) $eventData['end_date'] : '';

        try {
            $startsAt = new DateTimeImmutable($startDate);
            $endsAt = new DateTimeImmutable($endDate);
        } catch (\Exception $e) {
            throw InvalidDateFormatException::forField('start_date/end_date', $startDate . ' / ' . $endDate, $e);
        }

        $event = new Event(
            EventId::fromProviderId($baseEventId),
            $title,
            $startsAt,
            $endsAt,
            SellMode::ONLINE
        );

        /** @var array<int, array<string, mixed>> $zones */
        $zones = is_array($eventData['zones'] ?? null) ? $eventData['zones'] : [];

        foreach ($zones as $zoneData) {
            Assert::isArray($zoneData);
            Assert::keyExists($zoneData, 'name');
            Assert::keyExists($zoneData, 'price');
            Assert::keyExists($zoneData, 'capacity');

            $zoneName = is_scalar($zoneData['name']) ? (string) $zoneData['name'] : '';
            $zonePrice = is_numeric($zoneData['price']) ? (float) $zoneData['price'] : 0.0;
            $zoneCapacity = is_numeric($zoneData['capacity']) ? (int) $zoneData['capacity'] : 0;

            $event->addZone(new Zone(
                new ZoneName($zoneName),
                Price::fromFloat($zonePrice),
                $zoneCapacity
            ));
        }

        return $event;
    }
}
