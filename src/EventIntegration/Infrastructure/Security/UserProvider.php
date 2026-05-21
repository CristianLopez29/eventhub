<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
final class UserProvider implements UserProviderInterface
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function __construct()
    {
        $this->users['admin'] = new User(
            'admin',
            '$2y$13$xKVvHG2CwN2mz8YtIH.Fl.orKEoCUexD3t1Q6WuNp58jubh/yu9FW',
            ['ROLE_USER']
        );
    }

    /**
     * @param non-empty-string $username
     * @param array<string> $roles
     */
    public function addUser(string $username, string $hashedPassword, array $roles = ['ROLE_USER']): void
    {
        $this->users[$username] = new User($username, $hashedPassword, $roles);
    }

    public function userExists(string $username): bool
    {
        return isset($this->users[$username]);
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
        if (!isset($this->users[$identifier])) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $this->users[$identifier];
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
