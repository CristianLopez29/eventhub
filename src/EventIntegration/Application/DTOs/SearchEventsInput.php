<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\DTOs;

use DateTimeImmutable;

final readonly class SearchEventsInput
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt
    ) {
    }
}
