<?php

declare(strict_types=1);

namespace App\EventIntegration\Domain\ValueObjects;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class EventId
{
    /**
     * RFC 9562's DNS namespace. Any fixed namespace works; what matters is that it never
     * changes, because the v5 hash of a provider id is this event's primary key and a new
     * namespace would re-key every stored event.
     */
    private const string PROVIDER_ID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($this->value)) {
            throw new InvalidArgumentException('Invalid UUID format for EventId');
        }
    }

    public static function fromProviderId(string $providerId): self
    {
        $uuid = Uuid::v5(Uuid::fromString(self::PROVIDER_ID_NAMESPACE), $providerId);

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
