<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Acceptance;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginThrottlingAcceptanceTest extends WebTestCase
{
    private const int MAX_ATTEMPTS = 5;

    public function test_should_return_429_after_too_many_failed_logins(): void
    {
        $client = $this->clientFromItsOwnAddress();
        $username = 'throttle-probe-' . bin2hex(random_bytes(8));

        foreach (range(1, self::MAX_ATTEMPTS) as $attempt) {
            $this->attemptLogin($client, $username);
            self::assertResponseStatusCodeSame(401, sprintf('Attempt %d should still be allowed', $attempt));
        }

        $this->attemptLogin($client, $username);

        self::assertResponseStatusCodeSame(429);
    }

    public function test_should_keep_the_envelope_when_throttled(): void
    {
        $client = $this->clientFromItsOwnAddress();
        $username = 'throttle-envelope-' . bin2hex(random_bytes(8));

        foreach (range(1, self::MAX_ATTEMPTS + 1) as $ignored) {
            $this->attemptLogin($client, $username);
        }

        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseStatusCodeSame(429);
        self::assertNull($response['data']);
        self::assertSame('TOO_MANY_REQUESTS', $response['error']['code']);
    }

    /**
     * Symfony throttles per username *and* per source IP, the latter at five times the
     * username limit. Every test shares 127.0.0.1, so a test that deliberately exhausts
     * the limiter would exhaust it for the whole suite. Each one gets its own address
     * from the documentation range instead, which also makes the test order-independent.
     */
    private function clientFromItsOwnAddress(): KernelBrowser
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', sprintf('203.0.113.%d', random_int(1, 254)));

        return $client;
    }

    private function attemptLogin(KernelBrowser $client, string $username): void
    {
        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => $username, 'password' => 'wrong-password'], JSON_THROW_ON_ERROR)
        );
    }
}
