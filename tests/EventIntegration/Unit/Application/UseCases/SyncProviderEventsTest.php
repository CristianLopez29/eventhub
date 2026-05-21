<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Application\UseCases;

use App\EventIntegration\Application\DTOs\SyncEventsInput;
use App\EventIntegration\Application\UseCases\SyncProviderEvents;
use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SyncProviderEventsTest extends TestCase
{
    private EventRepositoryInterface&MockObject $eventRepository;
    private LoggerInterface&MockObject $logger;
    private SyncProviderEvents $useCase;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createMock(EventRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->useCase = new SyncProviderEvents($this->eventRepository, $this->logger);
    }

    #[Test]
    public function should_insert_new_online_events(): void
    {
        $input = new SyncEventsInput([
            [
                'base_event_id' => 'evt-1',
                'title' => 'Concert A',
                'start_date' => '2024-07-01 20:00:00',
                'end_date' => '2024-07-01 23:00:00',
                'sell_mode' => 'online',
                'zones' => [
                    ['name' => 'General', 'price' => 30.00, 'capacity' => 100],
                ],
            ],
        ]);

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
            ->with($this->callback(function (Event $event): bool {
                return $event->title() === 'Concert A'
                    && $event->isOnline();
            }));

        $result = $this->useCase->sync($input);

        self::assertSame(1, $result->insertedCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(0, $result->skippedCount);
    }

    #[Test]
    public function should_update_existing_online_events(): void
    {
        $input = new SyncEventsInput([
            [
                'base_event_id' => 'evt-1',
                'title' => 'Concert A Updated',
                'start_date' => '2024-07-01 20:00:00',
                'end_date' => '2024-07-01 23:00:00',
                'sell_mode' => 'online',
                'zones' => [],
            ],
        ]);

        $this->eventRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Updating existing event', $this->anything());

        $this->eventRepository
            ->expects($this->once())
            ->method('save');

        $result = $this->useCase->sync($input);

        self::assertSame(0, $result->insertedCount);
        self::assertSame(1, $result->updatedCount);
        self::assertSame(0, $result->skippedCount);
    }

    #[Test]
    public function should_skip_offline_events(): void
    {
        $input = new SyncEventsInput([
            [
                'base_event_id' => 'evt-1',
                'title' => 'Concert A',
                'start_date' => '2024-07-01 20:00:00',
                'end_date' => '2024-07-01 23:00:00',
                'sell_mode' => 'offline',
                'zones' => [],
            ],
        ]);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Skipping non-online event', ['sell_mode' => 'offline']);

        $this->eventRepository
            ->expects($this->never())
            ->method('exists');

        $this->eventRepository
            ->expects($this->never())
            ->method('save');

        $result = $this->useCase->sync($input);

        self::assertSame(0, $result->insertedCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(1, $result->skippedCount);
    }

    #[Test]
    public function should_save_multiple_online_events(): void
    {
        $input = new SyncEventsInput([
            [
                'base_event_id' => 'evt-1',
                'title' => 'Concert A',
                'start_date' => '2024-07-01 20:00:00',
                'end_date' => '2024-07-01 23:00:00',
                'sell_mode' => 'online',
                'zones' => [],
            ],
            [
                'base_event_id' => 'evt-2',
                'title' => 'Concert B',
                'start_date' => '2024-07-02 20:00:00',
                'end_date' => '2024-07-02 23:00:00',
                'sell_mode' => 'online',
                'zones' => [],
            ],
        ]);

        $this->eventRepository
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $this->eventRepository
            ->expects($this->exactly(2))
            ->method('save');

        $result = $this->useCase->sync($input);

        self::assertSame(2, $result->insertedCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(0, $result->skippedCount);
    }

    #[Test]
    public function should_persist_event_with_zones(): void
    {
        $input = new SyncEventsInput([
            [
                'base_event_id' => 'evt-1',
                'title' => 'Concert A',
                'start_date' => '2024-07-01 20:00:00',
                'end_date' => '2024-07-01 23:00:00',
                'sell_mode' => 'online',
                'zones' => [
                    ['name' => 'General', 'price' => 30.00, 'capacity' => 100],
                    ['name' => 'VIP', 'price' => 80.00, 'capacity' => 20],
                ],
            ],
        ]);

        $this->eventRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->eventRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Event $event): bool {
                $zones = $event->zones();

                return count($zones) === 2
                    && $zones[0]->name()->value() === 'General'
                    && $zones[1]->name()->value() === 'VIP'
                    && $event->minPrice()?->cents() === 3000
                    && $event->maxPrice()?->cents() === 8000;
            }));

        $this->useCase->sync($input);
    }

    #[Test]
    public function should_mixed_insert_update_and_skip(): void
    {
        $input = new SyncEventsInput([
            [
                'base_event_id' => 'evt-1',
                'title' => 'Concert A',
                'start_date' => '2024-07-01 20:00:00',
                'end_date' => '2024-07-01 23:00:00',
                'sell_mode' => 'online',
                'zones' => [],
            ],
            [
                'base_event_id' => 'evt-2',
                'title' => 'Concert B',
                'start_date' => '2024-07-02 20:00:00',
                'end_date' => '2024-07-02 23:00:00',
                'sell_mode' => 'offline',
                'zones' => [],
            ],
            [
                'base_event_id' => 'evt-3',
                'title' => 'Concert C',
                'start_date' => '2024-07-03 20:00:00',
                'end_date' => '2024-07-03 23:00:00',
                'sell_mode' => 'online',
                'zones' => [],
            ],
        ]);

        $this->eventRepository
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->eventRepository
            ->expects($this->exactly(2))
            ->method('save');

        $result = $this->useCase->sync($input);

        self::assertSame(1, $result->insertedCount);
        self::assertSame(1, $result->updatedCount);
        self::assertSame(1, $result->skippedCount);
    }
}
