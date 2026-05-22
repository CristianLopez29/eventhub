<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Application\UseCases;

use App\EventIntegration\Application\Contracts\EventCacheInvalidator;
use App\EventIntegration\Application\DTOs\SyncEventsInput;
use App\EventIntegration\Application\UseCases\SyncProviderEvents;
use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\Repositories\SaveEventRepository;
use App\Tests\EventIntegration\Builders\EventBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SyncProviderEventsTest extends TestCase
{
    private SaveEventRepository&MockObject $eventRepository;
    private LoggerInterface&MockObject $logger;
    private EventCacheInvalidator&MockObject $cacheInvalidator;
    private SyncProviderEvents $useCase;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createMock(SaveEventRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheInvalidator = $this->createMock(EventCacheInvalidator::class);
        $this->useCase = new SyncProviderEvents($this->eventRepository, $this->logger, $this->cacheInvalidator);
    }

    #[Test]
    public function should_insert_new_online_events(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('evt-1')
            ->withTitle('Concert A')
            ->withZone('General', 30.00, 100)
            ->build();

        $input = new SyncEventsInput([$event]);

        $this->eventRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Inserting new event', $this->anything());

        $this->eventRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Event $e) use ($event): bool {
                return $e->title() === 'Concert A' && $e->isOnline();
            }));

        $this->cacheInvalidator->expects($this->once())->method('invalidateSearchCache');

        $result = $this->useCase->sync($input);

        self::assertSame(1, $result->insertedCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(0, $result->skippedCount);
    }

    #[Test]
    public function should_update_existing_online_events(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('evt-1')
            ->withTitle('Concert A Updated')
            ->build();

        $input = new SyncEventsInput([$event]);

        $this->eventRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Updating existing event', $this->anything());

        $this->eventRepository->expects($this->once())->method('save');
        $this->cacheInvalidator->expects($this->once())->method('invalidateSearchCache');

        $result = $this->useCase->sync($input);

        self::assertSame(0, $result->insertedCount);
        self::assertSame(1, $result->updatedCount);
        self::assertSame(0, $result->skippedCount);
    }

    #[Test]
    public function should_skip_offline_events(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('evt-1')
            ->withTitle('Concert A')
            ->withSellMode(SellMode::OFFLINE)
            ->build();

        $input = new SyncEventsInput([$event]);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Skipping non-online event', $this->anything());

        $this->eventRepository->expects($this->never())->method('exists');
        $this->eventRepository->expects($this->never())->method('save');
        $this->cacheInvalidator->expects($this->once())->method('invalidateSearchCache');

        $result = $this->useCase->sync($input);

        self::assertSame(0, $result->insertedCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(1, $result->skippedCount);
    }

    #[Test]
    public function should_save_multiple_online_events(): void
    {
        $events = [
            EventBuilder::create()->withProviderId('evt-1')->withTitle('Concert A')->build(),
            EventBuilder::create()->withProviderId('evt-2')->withTitle('Concert B')->build(),
        ];

        $input = new SyncEventsInput($events);

        $this->eventRepository
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $this->eventRepository->expects($this->exactly(2))->method('save');
        $this->cacheInvalidator->expects($this->once())->method('invalidateSearchCache');

        $result = $this->useCase->sync($input);

        self::assertSame(2, $result->insertedCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(0, $result->skippedCount);
    }

    #[Test]
    public function should_persist_event_with_zones(): void
    {
        $event = EventBuilder::create()
            ->withProviderId('evt-1')
            ->withTitle('Concert A')
            ->withZone('General', 30.00, 100)
            ->withZone('VIP', 80.00, 20)
            ->build();

        $input = new SyncEventsInput([$event]);

        $this->eventRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->eventRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Event $e): bool {
                $zones = $e->zones();

                return count($zones) === 2
                    && $zones[0]->name()->value() === 'General'
                    && $zones[1]->name()->value() === 'VIP'
                    && $e->minPrice()?->cents() === 3000
                    && $e->maxPrice()?->cents() === 8000;
            }));

        $this->cacheInvalidator->expects($this->once())->method('invalidateSearchCache');

        $this->useCase->sync($input);
    }

    #[Test]
    public function should_mixed_insert_update_and_skip(): void
    {
        $events = [
            EventBuilder::create()->withProviderId('evt-1')->withTitle('Concert A')->build(),
            EventBuilder::create()->withProviderId('evt-2')->withTitle('Concert B')->withSellMode(SellMode::OFFLINE)->build(),
            EventBuilder::create()->withProviderId('evt-3')->withTitle('Concert C')->build(),
        ];

        $input = new SyncEventsInput($events);

        $this->eventRepository
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->eventRepository->expects($this->exactly(2))->method('save');
        $this->cacheInvalidator->expects($this->once())->method('invalidateSearchCache');

        $result = $this->useCase->sync($input);

        self::assertSame(1, $result->insertedCount);
        self::assertSame(1, $result->updatedCount);
        self::assertSame(1, $result->skippedCount);
    }
}
