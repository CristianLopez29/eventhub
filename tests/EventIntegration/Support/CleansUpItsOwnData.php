<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Support;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Leaves the database exactly as the test found it.
 *
 * The suite used to TRUNCATE events and zones, which is only safe while the connection
 * happens to point at a throwaway schema: a mistyped DATABASE_URL, a missing dbname_suffix
 * or a CI job pointed at the wrong host and the run destroys real rows.
 *
 * Instead each test snapshots the ids that already existed and deletes only what it added.
 * Legitimate data survives, and no test fixture is left behind when the run finishes.
 */
trait CleansUpItsOwnData
{
    /** @var list<string> */
    private array $preexistingEventIds = [];

    /** @var list<string> */
    private array $preexistingUsernames = [];

    private bool $rowsRemembered = false;

    /**
     * Idempotent: the first call in a test wins, so a helper that both authenticates and
     * snapshots cannot overwrite a baseline taken earlier and orphan its own fixtures.
     */
    protected function rememberExistingRows(): void
    {
        if ($this->rowsRemembered) {
            return;
        }

        $this->rowsRemembered = true;
        $connection = $this->testConnection();

        /** @var list<string> $eventIds */
        $eventIds = $connection->fetchFirstColumn('SELECT id FROM events');
        $this->preexistingEventIds = $eventIds;

        /** @var list<string> $usernames */
        $usernames = $connection->fetchFirstColumn('SELECT username FROM users');
        $this->preexistingUsernames = $usernames;
    }

    protected function removeRowsCreatedByThisTest(): void
    {
        if (!$this->rowsRemembered) {
            return;
        }

        // zones ride along: the FK is ON DELETE CASCADE.
        $this->deleteRowsAddedTo('events', 'id', $this->preexistingEventIds);
        $this->deleteRowsAddedTo('users', 'username', $this->preexistingUsernames);
    }

    /** @param list<string> $preexistingValues */
    private function deleteRowsAddedTo(string $table, string $keyColumn, array $preexistingValues): void
    {
        $connection = $this->testConnection();

        if ($preexistingValues === []) {
            $connection->executeStatement(sprintf('DELETE FROM %s', $table));

            return;
        }

        $connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s NOT IN (?)', $table, $keyColumn),
            [$preexistingValues],
            [ArrayParameterType::STRING]
        );
    }

    private function testConnection(): Connection
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
