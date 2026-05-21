<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string $username
     * @param array<string> $roles
     */
    public function __construct(
        private string $username,
        private string $password,
        private array $roles = ['ROLE_USER'],
    ) {
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        /** @var non-empty-string */
        return $this->username;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
    }
}
