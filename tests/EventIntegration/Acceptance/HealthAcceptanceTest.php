<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Acceptance;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthAcceptanceTest extends WebTestCase
{
    public function test_should_answer_liveness_without_a_token(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);

        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertNull($response['error']);
        self::assertSame('ok', $response['data']['status']);
    }

    public function test_should_report_every_dependency_as_reachable(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health/ready');

        self::assertResponseStatusCodeSame(200);

        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ready', $response['data']['status']);
        self::assertTrue($response['data']['checks']['database']);
        self::assertTrue($response['data']['checks']['cache']);
    }

    public function test_should_keep_health_endpoints_outside_the_jwt_firewall(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderNotSame('WWW-Authenticate', 'Bearer');
    }
}
