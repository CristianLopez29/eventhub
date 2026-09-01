<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Acceptance;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DocsAcceptanceTest extends WebTestCase
{
    public function test_should_serve_swagger_ui_without_credentials(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('swagger-ui', (string) $client->getResponse()->getContent());
    }

    public function test_should_serve_the_raw_openapi_document(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();

        $document = json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('EventHub API', $document['info']['title']);
        self::assertArrayHasKey('/events', $document['paths']);
    }

    public function test_should_send_a_swagger_compatible_csp_on_the_documentation_page(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc');

        $csp = $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertNotNull($csp);
        self::assertStringContainsString("script-src 'self'", $csp);
    }

    public function test_should_send_the_strict_security_headers_on_every_api_response(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        $response = $client->getResponse();
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame("default-src 'none'; frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }
}
