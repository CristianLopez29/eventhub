<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\Transformers;

use App\EventIntegration\Domain\Entities\Event;

final class EventTransformer
{
    /** @return array<string, mixed> */
    public function transform(Event $event): array
    {
        $minPrice = $event->minPrice();
        $maxPrice = $event->maxPrice();
        $startsAt = $event->startsAt();
        $endsAt = $event->endsAt();

        return [
            'id' => $event->id()->value(),
            'title' => $event->title(),
            'start_date' => $startsAt->format('Y-m-d'),
            'start_time' => $startsAt->format('H:i:s'),
            'end_date' => $endsAt->format('Y-m-d'),
            'end_time' => $endsAt->format('H:i:s'),
            'min_price' => $minPrice !== null ? $minPrice->toFloat() : null,
            'max_price' => $maxPrice !== null ? $maxPrice->toFloat() : null,
        ];
    }

    /**
     * @param Event[] $events
     * @return array<string, mixed>
     */
    public function transformCollection(array $events): array
    {
        $transformedEvents = [];

        foreach ($events as $event) {
            $transformedEvents[] = $this->transform($event);
        }

        return [
            'data' => [
                'events' => $transformedEvents,
            ],
            'error' => null,
        ];
    }
}
