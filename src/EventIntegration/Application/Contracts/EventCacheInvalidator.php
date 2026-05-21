<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\Contracts;

interface EventCacheInvalidator
{
    public function invalidateSearchCache(): void;
}
