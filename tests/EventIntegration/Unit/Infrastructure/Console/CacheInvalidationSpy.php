<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Infrastructure\Console;

use App\EventIntegration\Application\Contracts\EventCacheInvalidator;

final class CacheInvalidationSpy implements EventCacheInvalidator
{
    private bool $invalidated = false;

    public function invalidateSearchCache(): void
    {
        $this->invalidated = true;
    }

    public function wasInvalidated(): bool
    {
        return $this->invalidated;
    }
}
