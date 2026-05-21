<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\Repositories;

interface ProviderClientInterface
{
    /** @return array<array<string, mixed>> */
    public function fetchEvents(): array;
}
