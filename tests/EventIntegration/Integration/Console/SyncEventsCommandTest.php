<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Integration\Console;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\Repositories\ProviderClientInterface;
use App\EventIntegration\Domain\Repositories\SaveEventRepository;
use App\EventIntegration\Domain\Repositories\SearchEventsRepository;
use App\EventIntegration\Infrastructure\Cache\RedisCachedEventRepository;
use App\EventIntegration\Infrastructure\Repositories\ProviderClient;
use App\Tests\EventIntegration\Builders\EventBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncEventsCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private SaveEventRepository $writeRepository;
    private SearchEventsRepository $readRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('app:sync-events');
        $this->commandTester = new CommandTester($command);

        $this->writeRepository = self::getContainer()->get(SaveEventRepository::class);
        $this->readRepository = self::getContainer()->get(SearchEventsRepository::class);

        $this->cleanDatabase();
        $this->clearCache();
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

    private function clearCache(): void
    {
        $cachedRepository = self::getContainer()->get(RedisCachedEventRepository::class);
        $cachedRepository->invalidateSearchCache();
    }

    public function test_should_insert_new_events_from_provider(): void
    {
        $providerClient = new class implements ProviderClientInterface {
            /** @return Event[] */
            public function fetchEvents(): array
            {
                return [
                    EventBuilder::create()
                        ->withProviderId('provider-evt-1')
                        ->withTitle('Provider Concert')
                        ->withStartsAt(new DateTimeImmutable('2024-08-01 20:00:00'))
                        ->withEndsAt(new DateTimeImmutable('2024-08-01 23:00:00'))
                        ->withZone('General', 50.00, 200)
                        ->build(),
                ];
            }
        };

        self::getContainer()->set(ProviderClient::class, $providerClient);

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Fetched 1 event(s) from provider.', $output);
        self::assertStringContainsString('Inserted: 1 | Updated: 0 | Skipped (offline): 0', $output);
        self::assertStringContainsString('Redis cache purged', $output);

        $events = $this->readRepository->searchByDateRange(
            new DateTimeImmutable('2024-08-01 00:00:00'),
            new DateTimeImmutable('2024-08-01 23:59:59')
        );

        self::assertCount(1, $events);
        self::assertSame('Provider Concert', $events[0]->title());
    }

    public function test_should_update_existing_events(): void
    {
        $existingEvent = EventBuilder::create()
            ->withProviderId('provider-evt-2')
            ->withTitle('Old Title')
            ->withStartsAt(new DateTimeImmutable('2024-09-01 20:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-09-01 23:00:00'))
            ->build();

        $this->writeRepository->save($existingEvent);

        $providerClient = new class implements ProviderClientInterface {
            /** @return Event[] */
            public function fetchEvents(): array
            {
                return [
                    EventBuilder::create()
                        ->withProviderId('provider-evt-2')
                        ->withTitle('Updated Title')
                        ->withStartsAt(new DateTimeImmutable('2024-09-01 20:00:00'))
                        ->withEndsAt(new DateTimeImmutable('2024-09-01 23:00:00'))
                        ->build(),
                ];
            }
        };

        self::getContainer()->set(ProviderClient::class, $providerClient);

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Inserted: 0 | Updated: 1 | Skipped (offline): 0', $output);

        $events = $this->readRepository->searchByDateRange(
            new DateTimeImmutable('2024-09-01 00:00:00'),
            new DateTimeImmutable('2024-09-01 23:59:59')
        );

        self::assertCount(1, $events);
        self::assertSame('Updated Title', $events[0]->title());
    }

    public function test_should_skip_offline_events(): void
    {
        $providerClient = new class implements ProviderClientInterface {
            /** @return Event[] */
            public function fetchEvents(): array
            {
                return [
                    EventBuilder::create()
                        ->withProviderId('provider-evt-3')
                        ->withTitle('Offline Concert')
                        ->withStartsAt(new DateTimeImmutable('2024-10-01 20:00:00'))
                        ->withEndsAt(new DateTimeImmutable('2024-10-01 23:00:00'))
                        ->withSellMode(SellMode::OFFLINE)
                        ->build(),
                ];
            }
        };

        self::getContainer()->set(ProviderClient::class, $providerClient);

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Inserted: 0 | Updated: 0 | Skipped (offline): 1', $output);

        $events = $this->readRepository->searchByDateRange(
            new DateTimeImmutable('2024-10-01 00:00:00'),
            new DateTimeImmutable('2024-10-01 23:59:59')
        );

        self::assertCount(0, $events);
    }

    public function test_should_handle_provider_failure_gracefully(): void
    {
        $providerClient = new class implements ProviderClientInterface {
            /** @return Event[] */
            public function fetchEvents(): array
            {
                return [];
            }
        };

        self::getContainer()->set(ProviderClient::class, $providerClient);

        $application = new Application(self::$kernel);
        $command = $application->find('app:sync-events');
        $this->commandTester = new CommandTester($command);

        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Provider returned no events or API failed', $output);
    }
}
