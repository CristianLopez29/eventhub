<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Controllers;

use LogicException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Declares the route that the `json_login` firewall uses as its `check_path`.
 *
 * The body is never executed: the authenticator handles POST /login and answers through
 * Lexik's success or failure handler before the request reaches a controller. Symfony
 * still requires the path to resolve to a route, which is the only reason this class exists.
 */
final readonly class LoginController
{
    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function __invoke(): never
    {
        throw new LogicException('POST /login is handled by the json_login firewall and never reaches this controller.');
    }
}
