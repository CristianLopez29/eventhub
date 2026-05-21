<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Console;

use App\EventIntegration\Application\Contracts\EventCacheInvalidator;
use App\EventIntegration\Application\DTOs\SyncEventsInput;
use App\EventIntegration\Application\UseCases\SyncProviderEvents;
use App\EventIntegration\Domain\Repositories\ProviderClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sync-events',
    description: 'Synchronize events from the external provider into the local database',
)]
final class SyncEventsCommand extends Command
{
    public function __construct(
        private readonly ProviderClientInterface $providerClient,
        private readonly SyncProviderEvents $syncProviderEvents,
        private readonly EventCacheInvalidator $cacheInvalidator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Fetching events from provider...');

        $events = $this->providerClient->fetchEvents();

        if ($events === []) {
            $output->writeln('<comment>Provider returned no events or API failed. No changes applied.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Fetched %d event(s) from provider.', count($events)));

        $result = $this->syncProviderEvents->sync(new SyncEventsInput($events));

        $output->writeln(sprintf(
            'Inserted: %d | Updated: %d | Skipped (offline): %d',
            $result->insertedCount,
            $result->updatedCount,
            $result->skippedCount
        ));

        $this->cacheInvalidator->invalidateSearchCache();

        $output->writeln('<info>Events synchronized successfully. Redis cache purged.</info>');

        return Command::SUCCESS;
    }
}
