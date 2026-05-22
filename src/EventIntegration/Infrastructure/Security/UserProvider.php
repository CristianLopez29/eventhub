<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
final class UserProvider implements UserProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Unsupported user type');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        /** @var array{username: non-empty-string, password: string, roles: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT username, password, roles FROM users WHERE username = ?',
            [$identifier]
        );

        if ($row === false) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        /** @var array<string> $roles */
        $roles = json_decode($row['roles'], true);

        return new User($row['username'], $row['password'], $roles);
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
