<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Infrastructure\Repositories;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\ValueObjects\EventId;
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

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var Event[] $events */
        self::assertCount(4, $events);

        // Rock Festival: plan_id=1001 matches base_plan_id=1001
        self::assertTrue($events[0]->id()->equals(EventId::fromProviderId('1001')));
        self::assertSame('Rock Festival 2024', $events[0]->title());
        self::assertTrue($events[0]->isOnline());
        self::assertSame('2024-08-15', $events[0]->startsAt()->format('Y-m-d'));
        self::assertSame('2024-08-15', $events[0]->endsAt()->format('Y-m-d'));

        $zones0 = $events[0]->zones();
        self::assertCount(3, $zones0);
        self::assertSame('General Admission', $zones0[0]->name()->value());
        self::assertSame(45.0, $zones0[0]->price()->toFloat());
        self::assertSame(500, $zones0[0]->capacity());

        // Jazz Night Special has two plans: each gets its own plan_id (2001, 2002)
        self::assertTrue($events[1]->id()->equals(EventId::fromProviderId('2001')));
        self::assertSame('Jazz Night Special', $events[1]->title());
        self::assertCount(1, $events[1]->zones());

        self::assertTrue($events[2]->id()->equals(EventId::fromProviderId('2002')));
        self::assertSame('Jazz Night Special', $events[2]->title());
        self::assertCount(1, $events[2]->zones());

        // Symphony Orchestra: plan_id=3001
        self::assertTrue($events[3]->id()->equals(EventId::fromProviderId('3001')));
        self::assertSame('Symphony Orchestra Gala', $events[3]->title());
        self::assertCount(2, $events[3]->zones());
    }

    public function test_should_parse_real_provider_xml_response_2_with_offline_events(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../../../../resources/response_2.xml');
        self::assertNotFalse($xml);

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var Event[] $events */
        self::assertCount(4, $events);

        $onlineEvents = array_filter($events, static fn (Event $e): bool => $e->isOnline());
        $offlineEvents = array_filter($events, static fn (Event $e): bool => !$e->isOnline());
        self::assertCount(3, $onlineEvents);
        self::assertCount(1, $offlineEvents);
    }

    public function test_should_parse_real_provider_xml_response_3(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../../../../resources/response_3.xml');
        self::assertNotFalse($xml);

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var Event[] $events */
        self::assertCount(4, $events);

        $rockId = EventId::fromProviderId('1001');
        $rockEvent = array_values(array_filter($events, static fn (Event $e): bool => $e->id()->equals($rockId)))[0];
        $rockZones = $rockEvent->zones();
        self::assertSame(400, $rockZones[0]->capacity());
        self::assertSame(0, $rockZones[1]->capacity());
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

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var Event[] $events */
        self::assertCount(1, $events);
        self::assertTrue($events[0]->id()->equals(EventId::fromProviderId('291')));
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

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var Event[] $events */
        self::assertCount(1, $events);
        $zones = $events[0]->zones();
        self::assertCount(1, $zones);
        self::assertSame('Good Zone', $zones[0]->name()->value());
        self::assertSame(15.0, $zones[0]->price()->toFloat());
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

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        /** @var Event[] $events */
        self::assertCount(1, $events);
        $zones = $events[0]->zones();
        self::assertCount(1, $zones);
        self::assertSame('Good Zone', $zones[0]->name()->value());
    }

    public function test_should_return_empty_array_for_empty_planList(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <planList version="1.0">
           <output>
           </output>
        </planList>';

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
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

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $client = new ProviderClient('http://provider:8080/events.xml', $this->logger, $httpClient);

        $events = $client->fetchEvents();

        self::assertSame([], $events);
    }
}
