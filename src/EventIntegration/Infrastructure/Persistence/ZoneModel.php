<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'zones')]
class ZoneModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $priceCents;

    #[ORM\Column(type: 'integer')]
    private int $capacity;

    #[ORM\ManyToOne(targetEntity: EventModel::class, inversedBy: 'zones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EventModel $event = null;

    public function __construct(
        string $name,
        int $priceCents,
        int $capacity
    ) {
        $this->name = $name;
        $this->priceCents = $priceCents;
        $this->capacity = $capacity;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function priceCents(): int
    {
        return $this->priceCents;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function event(): ?EventModel
    {
        return $this->event;
    }

    public function setEvent(?EventModel $event): void
    {
        $this->event = $event;
    }
}
