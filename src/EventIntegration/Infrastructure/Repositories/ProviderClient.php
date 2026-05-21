<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Repositories;

use App\EventIntegration\Domain\Repositories\ProviderClientInterface;
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

    /** @return array<array<string, mixed>> */
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

        $events = [];

        if (!isset($xml->output->base_plan)) {
            $this->logger->warning('No base_plan elements found in XML');

            return [];
        }

        foreach ($xml->output->base_plan as $basePlanNode) {
            $basePlanData = $this->parseBasePlanNode($basePlanNode);

            if ($basePlanData !== null) {
                foreach ($basePlanData as $eventData) {
                    $events[] = $eventData;
                }
            }
        }

        return $events;
    }

    /**
     * A base_plan can contain multiple plan elements.
     * Each plan becomes an independent event.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function parseBasePlanNode(\SimpleXMLElement $basePlanNode): ?array
    {
        $baseEventId = (string) ($basePlanNode['base_plan_id'] ?? '');
        $title = (string) ($basePlanNode['title'] ?? '');
        $sellMode = (string) ($basePlanNode['sell_mode'] ?? '');

        if ($baseEventId === '' || $title === '') {
            return null;
        }

        if (!isset($basePlanNode->plan)) {
            return null;
        }

        $events = [];

        foreach ($basePlanNode->plan as $planNode) {
            $eventData = $this->parsePlanNode($planNode, $baseEventId, $title, $sellMode);

            if ($eventData !== null) {
                $events[] = $eventData;
            }
        }

        return $events;
    }

    /** @return array<string, mixed>|null */
    private function parsePlanNode(\SimpleXMLElement $planNode, string $baseEventId, string $title, string $sellMode): ?array
    {
        $startDate = (string) ($planNode['plan_start_date'] ?? '');
        $endDate = (string) ($planNode['plan_end_date'] ?? '');

        if ($startDate === '' || $endDate === '') {
            return null;
        }

        $zones = [];

        if (isset($planNode->zone)) {
            foreach ($planNode->zone as $zoneNode) {
                $zoneData = $this->parseZoneNode($zoneNode);

                if ($zoneData !== null) {
                    $zones[] = $zoneData;
                }
            }
        }

        return [
            'base_event_id' => $baseEventId,
            'title' => $title,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'sell_mode' => $sellMode,
            'zones' => $zones,
        ];
    }

    /** @return array<string, mixed>|null */
    private function parseZoneNode(\SimpleXMLElement $zoneNode): ?array
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

        return [
            'name' => $name,
            'price' => $priceValue,
            'capacity' => (int) $capacity,
        ];
    }
}
