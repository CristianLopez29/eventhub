<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Infrastructure\Console;

use App\EventIntegration\Application\Contracts\EventCacheInvalidator;
use App\EventIntegration\Application\UseCases\SyncProviderEvents;
use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\Repositories\ProviderClientInterface;
use App\EventIntegration\Domain\Repositories\SaveEventRepository;
use App\EventIntegration\Infrastructure\Console\SyncEventsCommand;
use App\Tests\EventIntegration\Builders\EventBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncEventsCommandTest extends TestCase
{
    private ProviderClientInterface&MockObject $providerClient;
    private EventCacheInvalidator&MockObject $cacheInvalidator;
    private SyncEventsCommand $command;

    protected function setUp(): void
    {
        $this->providerClient = $this->createMock(ProviderClientInterface::class);
        $this->cacheInvalidator = $this->createMock(EventCacheInvalidator::class);

        $eventRepository = $this->createMock(SaveEventRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $eventRepository->method('exists')->willReturn(false);

        $syncProviderEvents = new SyncProviderEvents($eventRepository, $logger, $this->cacheInvalidator);

        $this->command = new SyncEventsCommand(
            $this->providerClient,
            $syncProviderEvents,
        );
    }

    #[Test]
    public function should_sync_events_and_purge_cache(): void
    {
        $events = [
            EventBuilder::create()->withProviderId('evt-1')->withTitle('Concert A')->build(),
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
            EventBuilder::create()->withProviderId('evt-1')->withTitle('Concert A')->build(),
            EventBuilder::create()->withProviderId('evt-2')->withTitle('Concert B')->withSellMode(SellMode::OFFLINE)->build(),
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
