<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\DTOs;

final readonly class SyncResult
{
    public function __construct(
        public int $insertedCount,
        public int $updatedCount,
        public int $skippedCount,
    ) {
    }
}
