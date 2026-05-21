<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final readonly class LoginController
{
    #[Route('/login', methods: ['POST'])]
    public function __invoke(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(
                [
                    'error' => [
                        'code' => 'INVALID_CREDENTIALS',
                        'message' => 'Invalid credentials.',
                    ],
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new JsonResponse([
            'data' => [
                'user' => $user->getUserIdentifier(),
            ],
            'error' => null,
        ]);
    }
}
