<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Acceptance;

use App\EventIntegration\Domain\Repositories\SaveEventRepository;
use App\EventIntegration\Infrastructure\Cache\RedisCachedEventRepository;
use App\Tests\EventIntegration\Builders\EventBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchEventsAcceptanceTest extends WebTestCase
{
    private function cleanDatabase(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $entityManager->getConnection();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE zones');
        $connection->executeStatement('TRUNCATE TABLE events');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $entityManager->clear();
    }

    private function clearCache(): void
    {
        $cachedRepository = self::getContainer()->get(RedisCachedEventRepository::class);
        $cachedRepository->invalidateSearchCache();
    }

    private function authenticateClient(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $entityManager->getConnection();

        // Ensure the test user exists (INSERT IGNORE is safe across multiple tests in the same run)
        $hashedPassword = password_hash('test_pass', PASSWORD_BCRYPT, ['cost' => 4]);
        $connection->executeStatement(
            'INSERT IGNORE INTO users (username, password, roles) VALUES (?, ?, ?)',
            ['admin', $hashedPassword, '["ROLE_USER"]']
        );

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin', 'password' => 'test_pass'])
        );

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['data']['token'] ?? '';

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
    }

    public function test_should_return_401_in_the_envelope_when_token_missing(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();
        $this->clearCache();

        $client->request('GET', '/events?starts_at=2024-06-01T00:00:00&ends_at=2024-06-30T23:59:59');

        self::assertResponseStatusCodeSame(401);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertNull($response['data']);
        self::assertSame('AUTHENTICATION_REQUIRED', $response['error']['code']);
    }

    public function test_should_return_401_in_the_envelope_when_token_invalid(): void
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer not-a-real-token');

        $client->request('GET', '/events?starts_at=2024-06-01T00:00:00&ends_at=2024-06-30T23:59:59');

        self::assertResponseStatusCodeSame(401);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertNull($response['data']);
        self::assertSame('INVALID_TOKEN', $response['error']['code']);
    }

    public function test_should_return_401_in_the_envelope_when_credentials_are_wrong(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin', 'password' => 'wrong-password'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertNull($response['data']);
        self::assertSame('INVALID_CREDENTIALS', $response['error']['code']);
    }

    public function test_should_return_404_in_the_envelope_for_an_unknown_route(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);

        $client->request('GET', '/does-not-exist');

        self::assertResponseStatusCodeSame(404);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertNull($response['data']);
        self::assertSame('NOT_FOUND', $response['error']['code']);
    }

    public function test_should_return_405_in_the_envelope_when_method_not_allowed(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);

        $client->request('POST', '/events');

        self::assertResponseStatusCodeSame(405);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertNull($response['data']);
        self::assertSame('METHOD_NOT_ALLOWED', $response['error']['code']);
    }

    public function test_should_wrap_the_login_token_in_the_envelope(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertNull($response['error']);
        self::assertIsArray($response['data']);
        self::assertArrayHasKey('token', $response['data']);
        self::assertNotSame('', $response['data']['token']);
    }

    public function test_should_return_200_with_events_in_range(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);
        $this->cleanDatabase();
        $this->clearCache();

        $repository = self::getContainer()->get(SaveEventRepository::class);

        $event = EventBuilder::create()
            ->withProviderId('event-123')
            ->withTitle('Test Concert')
            ->withStartsAt(new DateTimeImmutable('2024-06-15 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-15 12:00:00'))
            ->withZone('General', 30.0, 100)
            ->withZone('VIP', 100.0, 50)
            ->build();

        $repository->save($event);

        $client->request('GET', '/events?starts_at=2024-06-01T00:00:00&ends_at=2024-06-30T23:59:59');

        self::assertResponseStatusCodeSame(200);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($response);
        self::assertArrayHasKey('data', $response);
        self::assertIsArray($response['data']);
        self::assertArrayHasKey('events', $response['data']);
        self::assertCount(1, $response['data']['events']);
        self::assertNull($response['error']);

        $eventData = $response['data']['events'][0];
        self::assertSame('Test Concert', $eventData['title']);
        self::assertSame('2024-06-15', $eventData['start_date']);
        self::assertSame('10:00:00', $eventData['start_time']);
        self::assertSame('2024-06-15', $eventData['end_date']);
        self::assertSame('12:00:00', $eventData['end_time']);
        self::assertEqualsWithDelta(30.0, $eventData['min_price'], 0.01);
        self::assertEqualsWithDelta(100.0, $eventData['max_price'], 0.01);
    }

    public function test_should_return_200_with_empty_data_when_no_events(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);
        $this->cleanDatabase();
        $this->clearCache();

        $client->request('GET', '/events?starts_at=2025-01-01T00:00:00&ends_at=2025-12-31T23:59:59');

        self::assertResponseStatusCodeSame(200);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($response);
        self::assertArrayHasKey('data', $response);
        self::assertIsArray($response['data']);
        self::assertArrayHasKey('events', $response['data']);
        self::assertSame([], $response['data']['events']);
        self::assertNull($response['error']);
    }

    public function test_should_return_400_when_missing_starts_at(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);
        $this->cleanDatabase();
        $this->clearCache();

        $client->request('GET', '/events?ends_at=2024-06-30T23:59:59');

        self::assertResponseStatusCodeSame(400);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($response['error']);
        self::assertSame('INVALID_PARAMETERS', $response['error']['code']);
        self::assertStringContainsString('starts_at', $response['error']['message']);
        self::assertNull($response['data']);
    }

    public function test_should_return_400_when_missing_ends_at(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);
        $this->cleanDatabase();
        $this->clearCache();

        $client->request('GET', '/events?starts_at=2024-06-01T00:00:00');

        self::assertResponseStatusCodeSame(400);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($response['error']);
        self::assertSame('INVALID_PARAMETERS', $response['error']['code']);
        self::assertStringContainsString('ends_at', $response['error']['message']);
        self::assertNull($response['data']);
    }

    public function test_should_return_400_when_invalid_date_format(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);
        $this->cleanDatabase();
        $this->clearCache();

        $client->request('GET', '/events?starts_at=invalid&ends_at=2024-06-30T23:59:59');

        self::assertResponseStatusCodeSame(400);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($response['error']);
        self::assertSame('INVALID_DATE_FORMAT', $response['error']['code']);
        self::assertNull($response['data']);
    }

    public function test_should_return_400_when_date_has_invalid_values(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);
        $this->cleanDatabase();
        $this->clearCache();

        $client->request('GET', '/events?starts_at=2024-13-45T99:99:99&ends_at=2024-06-30T23:59:59');

        self::assertResponseStatusCodeSame(400);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('error', $response);
        self::assertIsArray($response['error']);
        self::assertSame('INVALID_DATE_FORMAT', $response['error']['code']);
        self::assertNull($response['data']);
    }

    public function test_should_return_multiple_events_sorted_by_date(): void
    {
        $client = static::createClient();
        $this->authenticateClient($client);
        $this->cleanDatabase();
        $this->clearCache();

        $repository = self::getContainer()->get(SaveEventRepository::class);

        $event1 = EventBuilder::create()
            ->withProviderId('event-1')
            ->withTitle('First Event')
            ->withStartsAt(new DateTimeImmutable('2024-06-10 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-10 12:00:00'))
            ->build();

        $event2 = EventBuilder::create()
            ->withProviderId('event-2')
            ->withTitle('Second Event')
            ->withStartsAt(new DateTimeImmutable('2024-06-20 10:00:00'))
            ->withEndsAt(new DateTimeImmutable('2024-06-20 12:00:00'))
            ->build();

        $repository->save($event1);
        $repository->save($event2);

        $client->request('GET', '/events?starts_at=2024-06-01T00:00:00&ends_at=2024-06-30T23:59:59');

        self::assertResponseStatusCodeSame(200);

        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('data', $response);
        self::assertArrayHasKey('events', $response['data']);
        self::assertCount(2, $response['data']['events']);
        self::assertNull($response['error']);
        self::assertSame('First Event', $response['data']['events'][0]['title']);
        self::assertSame('Second Event', $response['data']['events'][1]['title']);
    }
}
