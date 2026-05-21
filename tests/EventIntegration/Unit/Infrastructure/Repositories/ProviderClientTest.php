<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Infrastructure\Repositories;

use App\EventIntegration\Infrastructure\Repositories\ProviderClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProviderClientTest extends TestCase
{
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new NullLogger();
    }

    public function test_should_return_empty_array_on_http_error(): void
    {
        $responses = [
            new MockResponse('', ['http_code' => 500]),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        self::assertSame([], $events);
    }

    public function test_should_return_empty_array_on_connection_exception(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['error' => 'Connection refused']),
        ]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        self::assertSame([], $events);
    }

    public function test_should_return_empty_array_on_invalid_xml(): void
    {
        $responses = [
            new MockResponse('not xml at all'),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        self::assertSame([], $events);
    }

    public function test_should_parse_real_provider_xml_response_1(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../../../../resources/response_1.xml');
        self::assertNotFalse($xml);

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var array<int, array<string, mixed>> $events */
        self::assertCount(4, $events);

        self::assertSame('1001', $events[0]['base_event_id']);
        self::assertSame('Rock Festival 2024', $events[0]['title']);
        self::assertSame('online', $events[0]['sell_mode']);
        self::assertSame('2024-08-15T19:00:00', $events[0]['start_date']);
        self::assertSame('2024-08-15T23:00:00', $events[0]['end_date']);

        /** @var array<int, array<string, mixed>> $zones0 */
        $zones0 = $events[0]['zones'];
        self::assertCount(3, $zones0);
        self::assertSame('General Admission', $zones0[0]['name']);
        self::assertSame(45.0, $zones0[0]['price']);
        self::assertSame(500, $zones0[0]['capacity']);

        self::assertSame('1002', $events[1]['base_event_id']);
        self::assertSame('Jazz Night Special', $events[1]['title']);
        /** @var array<int, array<string, mixed>> $zones1 */
        $zones1 = $events[1]['zones'];
        self::assertCount(1, $zones1);

        self::assertSame('1002', $events[2]['base_event_id']);
        self::assertSame('Jazz Night Special', $events[2]['title']);
        /** @var array<int, array<string, mixed>> $zones2 */
        $zones2 = $events[2]['zones'];
        self::assertCount(1, $zones2);

        self::assertSame('1003', $events[3]['base_event_id']);
        self::assertSame('Symphony Orchestra Gala', $events[3]['title']);
        /** @var array<int, array<string, mixed>> $zones3 */
        $zones3 = $events[3]['zones'];
        self::assertCount(2, $zones3);
    }

    public function test_should_parse_real_provider_xml_response_2_with_offline_events(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../../../../resources/response_2.xml');
        self::assertNotFalse($xml);

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var array<int, array<string, mixed>> $events */
        self::assertCount(4, $events);

        $sellModes = array_column($events, 'sell_mode');
        self::assertContains('online', $sellModes);
        self::assertContains('offline', $sellModes);

        $onlineEvents = array_filter($events, static fn (array $e): bool => $e['sell_mode'] === 'online');
        self::assertCount(3, $onlineEvents);
    }

    public function test_should_parse_real_provider_xml_response_3(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../../../../resources/response_3.xml');
        self::assertNotFalse($xml);

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var array<int, array<string, mixed>> $events */
        self::assertCount(4, $events);

        $rockEvent = array_values(array_filter($events, static fn (array $e): bool => $e['base_event_id'] === '1001'))[0];
        /** @var array<int, array<string, mixed>> $rockZones */
        $rockZones = $rockEvent['zones'];
        self::assertSame(400, $rockZones[0]['capacity']);
        self::assertSame(0, $rockZones[1]['capacity']);
    }

    public function test_should_skip_base_plan_with_missing_id(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <planList version="1.0">
           <output>
              <base_plan base_plan_id="" sell_mode="online" title="Missing ID">
                 <plan plan_start_date="2021-06-30T21:00:00" plan_end_date="2021-06-30T22:00:00">
                    <zone zone_id="40" capacity="243" price="20.00" name="Platea" numbered="true" />
                 </plan>
              </base_plan>
              <base_plan base_plan_id="291" sell_mode="online" title="Valid Event">
                 <plan plan_start_date="2021-06-30T21:00:00" plan_end_date="2021-06-30T22:00:00">
                    <zone zone_id="40" capacity="243" price="20.00" name="Platea" numbered="true" />
                 </plan>
              </base_plan>
           </output>
        </planList>';

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var array<int, array<string, mixed>> $events */
        self::assertCount(1, $events);
        self::assertSame('291', $events[0]['base_event_id']);
    }

    public function test_should_skip_zone_with_invalid_price(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <planList version="1.0">
           <output>
              <base_plan base_plan_id="291" sell_mode="online" title="Event With Bad Zone">
                 <plan plan_start_date="2021-06-30T21:00:00" plan_end_date="2021-06-30T22:00:00">
                    <zone zone_id="40" capacity="243" price="not_a_number" name="Bad Zone" numbered="true" />
                    <zone zone_id="38" capacity="100" price="15.00" name="Good Zone" numbered="false" />
                 </plan>
              </base_plan>
           </output>
        </planList>';

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var array<int, array<string, mixed>> $events */
        self::assertCount(1, $events);
        /** @var array<int, array<string, mixed>> $zones */
        $zones = $events[0]['zones'];
        self::assertCount(1, $zones);
        self::assertSame('Good Zone', $zones[0]['name']);
        self::assertSame(15.0, $zones[0]['price']);
    }

    public function test_should_skip_zone_with_negative_price(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <planList version="1.0">
           <output>
              <base_plan base_plan_id="291" sell_mode="online" title="Event With Negative Price">
                 <plan plan_start_date="2021-06-30T21:00:00" plan_end_date="2021-06-30T22:00:00">
                    <zone zone_id="40" capacity="243" price="-10.00" name="Bad Zone" numbered="true" />
                    <zone zone_id="38" capacity="100" price="15.00" name="Good Zone" numbered="false" />
                 </plan>
              </base_plan>
           </output>
        </planList>';

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var array<int, array<string, mixed>> $events */
        self::assertCount(1, $events);
        /** @var array<int, array<string, mixed>> $zones */
        $zones = $events[0]['zones'];
        self::assertCount(1, $zones);
        self::assertSame('Good Zone', $zones[0]['name']);
    }

    public function test_should_return_empty_array_for_empty_planList(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <planList version="1.0">
           <output>
           </output>
        </planList>';

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        self::assertSame([], $events);
    }

    public function test_should_handle_base_plan_without_plans(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <planList version="1.0">
           <output>
              <base_plan base_plan_id="291" sell_mode="online" title="No Plans">
              </base_plan>
           </output>
        </planList>';

        $responses = [
            new MockResponse($xml),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        self::assertSame([], $events);
    }
}
