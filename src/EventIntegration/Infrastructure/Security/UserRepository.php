<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Security;

use Doctrine\DBAL\Connection;

final class UserRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param non-empty-string $username
     * @param array<string> $roles
     */
    public function addUser(string $username, string $hashedPassword, array $roles = ['ROLE_USER']): void
    {
        $this->connection->insert('users', [
            'username' => $username,
            'password' => $hashedPassword,
            'roles' => json_encode($roles, JSON_THROW_ON_ERROR),
        ]);
    }

    public function userExists(string $username): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE username = ?',
            [$username]
        );

        return is_numeric($count) && (int) $count > 0;
    }
}
