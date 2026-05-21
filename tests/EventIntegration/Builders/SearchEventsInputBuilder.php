<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Builders;

use App\EventIntegration\Application\DTOs\SearchEventsInput;
use DateTimeImmutable;

final class SearchEventsInputBuilder
{
    private DateTimeImmutable $startsAt;
    private DateTimeImmutable $endsAt;

    public function __construct()
    {
        $this->startsAt = new DateTimeImmutable('2024-06-01 00:00:00');
        $this->endsAt = new DateTimeImmutable('2024-06-30 23:59:59');
    }

    public static function create(): self
    {
        return new self();
    }

    public function withStartsAt(DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function withEndsAt(DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function build(): SearchEventsInput
    {
        return new SearchEventsInput($this->startsAt, $this->endsAt);
    }
}
