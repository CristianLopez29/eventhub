<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Repositories;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Entities\Zone;
use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\Repositories\ProviderClientInterface;
use App\EventIntegration\Domain\ValueObjects\EventId;
use App\EventIntegration\Domain\ValueObjects\Price;
use App\EventIntegration\Domain\ValueObjects\ZoneName;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ProviderClient implements ProviderClientInterface
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private string $providerUrl,
        private LoggerInterface $logger,
        ?HttpClientInterface $httpClient = null
    ) {
        $baseClient = $httpClient ?? \Symfony\Component\HttpClient\HttpClient::create([
            'timeout' => 30.0,
            'headers' => [
                'Accept' => 'application/xml',
            ],
        ]);

        $this->httpClient = new RetryableHttpClient(
            $baseClient,
            null,
            3,
            $this->logger
        );
    }

    /** @return Event[] */
    public function fetchEvents(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->providerUrl);
            $content = $response->getContent();

            return $this->parseXml($content);
        } catch (\Exception $exception) {
            $this->logger->error('Provider API request failed: ' . $exception->getMessage());

            return [];
        }
    }

    /** @return Event[] */
    private function parseXml(string $xmlContent): array
    {
        $previousValue = libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            libxml_use_internal_errors($previousValue);
            $this->logger->error('Failed to parse provider XML response');

            return [];
        }

        libxml_use_internal_errors($previousValue);

        if (!isset($xml->output->base_plan)) {
            $this->logger->warning('No base_plan elements found in XML');

            return [];
        }

        $events = [];

        foreach ($xml->output->base_plan as $basePlanNode) {
            foreach ($this->parseBasePlanNode($basePlanNode) as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * A base_plan can contain multiple plan elements; each plan becomes an independent event.
     *
     * @return Event[]
     */
    private function parseBasePlanNode(\SimpleXMLElement $basePlanNode): array
    {
        $baseEventId = (string) ($basePlanNode['base_plan_id'] ?? '');
        $title = (string) ($basePlanNode['title'] ?? '');
        $sellMode = (string) ($basePlanNode['sell_mode'] ?? '');

        if ($baseEventId === '' || $title === '') {
            return [];
        }

        if (!isset($basePlanNode->plan)) {
            return [];
        }

        $events = [];

        foreach ($basePlanNode->plan as $planNode) {
            $event = $this->parsePlanNode($planNode, $baseEventId, $title, $sellMode);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    private function parsePlanNode(\SimpleXMLElement $planNode, string $baseEventId, string $title, string $sellMode): ?Event
    {
        $startDate = (string) ($planNode['plan_start_date'] ?? '');
        $endDate = (string) ($planNode['plan_end_date'] ?? '');

        if ($startDate === '' || $endDate === '') {
            return null;
        }

        try {
            $startsAt = new DateTimeImmutable($startDate);
            $endsAt = new DateTimeImmutable($endDate);
        } catch (\Exception $e) {
            $this->logger->warning('Invalid date in plan, skipping', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // plan_id uniquely identifies each occurrence; base_plan_id identifies the event type.
        // Multiple plans under the same base_plan share the same base_plan_id but have different
        // dates — using plan_id prevents later plans from overwriting earlier ones in the DB.
        $planId = (string) ($planNode['plan_id'] ?? '');
        $eventId = $planId !== '' ? $planId : $baseEventId;

        $event = new Event(
            EventId::fromProviderId($eventId),
            $title,
            $startsAt,
            $endsAt,
            SellMode::tryFrom($sellMode) ?? SellMode::OFFLINE,
        );

        if (isset($planNode->zone)) {
            foreach ($planNode->zone as $zoneNode) {
                $zone = $this->parseZoneNode($zoneNode);

                if ($zone !== null) {
                    $event->addZone($zone);
                }
            }
        }

        return $event;
    }

    private function parseZoneNode(\SimpleXMLElement $zoneNode): ?Zone
    {
        $name = (string) ($zoneNode['name'] ?? '');
        $price = (string) ($zoneNode['price'] ?? '');
        $capacity = (string) ($zoneNode['capacity'] ?? '');

        if ($name === '' || $price === '' || $capacity === '') {
            return null;
        }

        if (!is_numeric($price)) {
            $this->logger->warning('Invalid price value, skipping zone', ['price' => $price, 'name' => $name]);

            return null;
        }

        if (!ctype_digit($capacity)) {
            $this->logger->warning('Invalid capacity value, skipping zone', ['capacity' => $capacity, 'name' => $name]);

            return null;
        }

        $priceValue = (float) $price;

        if ($priceValue < 0) {
            $this->logger->warning('Negative price value, skipping zone', ['price' => $price, 'name' => $name]);

            return null;
        }

        return new Zone(
            new ZoneName($name),
            Price::fromFloat($priceValue),
            (int) $capacity,
        );
    }
}
