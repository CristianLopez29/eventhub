<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Infrastructure\Console;

use App\EventIntegration\Application\Contracts\EventCacheInvalidator;
use App\EventIntegration\Application\UseCases\SyncProviderEvents;
use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use App\EventIntegration\Domain\Repositories\ProviderClientInterface;
use App\EventIntegration\Infrastructure\Console\SyncEventsCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncEventsCommandTest extends TestCase
{
    private ProviderClientInterface&MockObject $providerClient;
    private SyncEventsCommand $command;
    private EventCacheInvalidator&MockObject $cacheInvalidator;

    protected function setUp(): void
    {
        $this->providerClient = $this->createMock(ProviderClientInterface::class);
        $this->cacheInvalidator = $this->createMock(EventCacheInvalidator::class);

        $eventRepository = $this->createMock(EventRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $syncProviderEvents = new SyncProviderEvents($eventRepository, $logger);

        $this->command = new SyncEventsCommand(
            $this->providerClient,
            $syncProviderEvents,
            $this->cacheInvalidator,
        );
    }

    #[Test]
    public function should_sync_events_and_purge_cache(): void
    {
        $events = [
            [
                'base_event_id' => 'evt-1',
                'title' => 'Concert A',
                'start_date' => '2024-07-01 20:00:00',
                'end_date' => '2024-07-01 23:00:00',
                'sell_mode' => 'online',
                'zones' => [],
            ],
        ];

        $this->providerClient
            ->expects($this->once())
            ->method('fetchEvents')
            ->willReturn($events);

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidateSearchCache');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('Fetched 1 event(s) from provider.', $output);
        self::assertStringContainsString('Redis cache purged', $output);
    }

    #[Test]
    public function should_handle_empty_provider_response(): void
    {
        $this->providerClient
            ->expects($this->once())
            ->method('fetchEvents')
            ->willReturn([]);

        $this->cacheInvalidator
            ->expects($this->never())
            ->method('invalidateSearchCache');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('Provider returned no events or API failed', $output);
    }

    #[Test]
    public function should_purge_cache_after_successful_sync(): void
    {
        $events = [
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
        ];

        $this->providerClient
            ->expects($this->once())
            ->method('fetchEvents')
            ->willReturn($events);

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidateSearchCache');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
