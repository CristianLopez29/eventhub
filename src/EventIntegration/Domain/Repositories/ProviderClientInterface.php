<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Repositories;

use App\EventIntegration\Domain\Entities\Event;

interface ProviderClientInterface
{
    /** @return Event[] */
    public function fetchEvents(): array;
}
