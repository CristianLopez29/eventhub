<?php

declare(strict_types=1);

namespace App\EventIntegration\Application\DTOs;

use App\EventIntegration\Domain\Exceptions\InvalidDateRangeException;
use App\EventIntegration\Domain\Exceptions\InvalidPaginationException;
use DateTimeImmutable;

final readonly class SearchEventsInput
{
    public const int DEFAULT_PER_PAGE = 50;
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($this->endsAt < $this->startsAt) {
            throw InvalidDateRangeException::endsBeforeItStarts($this->startsAt, $this->endsAt);
        }

        if ($this->page < 1) {
            throw InvalidPaginationException::forPage($this->page);
        }

        if ($this->perPage < 1 || $this->perPage > self::MAX_PER_PAGE) {
            throw InvalidPaginationException::forPerPage($this->perPage, self::MAX_PER_PAGE);
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
