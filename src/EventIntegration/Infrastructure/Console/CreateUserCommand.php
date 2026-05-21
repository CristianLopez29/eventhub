<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Console;

use App\EventIntegration\Infrastructure\Security\UserProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user for JWT authentication',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private UserProvider $userProvider,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username')
            ->addArgument('password', InputArgument::REQUIRED, 'The password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');

        if (!is_string($username) || !is_string($password)) {
            $output->writeln('<error>Username and password must be strings.</error>');

            return Command::FAILURE;
        }

        if ($username === '' || $password === '') {
            $output->writeln('<error>Username and password cannot be empty.</error>');

            return Command::FAILURE;
        }

        if ($this->userProvider->userExists($username)) {
            $output->writeln(sprintf('<error>User "%s" already exists.</error>', $username));

            return Command::FAILURE;
        }

        $user = new \App\EventIntegration\Infrastructure\Security\User($username, '');
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);

        $this->userProvider->addUser($username, $hashedPassword);

        $output->writeln(sprintf('<info>User "%s" created successfully.</info>', $username));

        return Command::SUCCESS;
    }
}
