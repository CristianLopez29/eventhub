<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Health;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Redis;
use Throwable;

/**
 * Cheapest possible reachability check per backing service.
 *
 * Both probes swallow their exception on purpose: an unreachable dependency is the answer
 * the endpoint exists to report, not a 500.
 */
final readonly class DependencyProbe
{
    public function __construct(
        private Connection $connection,
        private Redis $redis,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array<string, bool> */
    public function run(): array
    {
        return [
            'database' => $this->check('database', fn (): mixed => $this->connection->executeQuery('SELECT 1')->fetchOne()),
            'cache' => $this->check('cache', fn (): mixed => $this->redis->ping()),
        ];
    }

    private function check(string $dependency, callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable $failure) {
            $this->logger->error('Readiness probe failed', [
                'dependency' => $dependency,
                'message' => $failure->getMessage(),
            ]);

            return false;
        }
    }
}
