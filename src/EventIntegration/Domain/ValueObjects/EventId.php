<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class EventId
{
    public function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($this->value)) {
            throw new InvalidArgumentException('Invalid UUID format for EventId');
        }
    }

    public static function fromProviderId(string $providerId): self
    {
        $uuid = Uuid::v5(Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'), $providerId);

        return new self($uuid->toRfc4122());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
