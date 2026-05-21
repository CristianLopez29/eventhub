<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Persistence;

use App\EventIntegration\Domain\Enums\SellMode;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'events')]
#[ORM\Index(name: 'idx_starts_at', columns: ['starts_at'])]
#[ORM\Index(name: 'idx_ends_at', columns: ['ends_at'])]
#[ORM\Index(name: 'idx_date_range', columns: ['starts_at', 'ends_at'])]
class EventModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(type: 'string', length: 20, enumType: SellMode::class)]
    private SellMode $sellMode;

    /** @var Collection<int, ZoneModel> */
    #[ORM\OneToMany(targetEntity: ZoneModel::class, mappedBy: 'event', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $zones;

    public function __construct(
        string $id,
        string $title,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        SellMode $sellMode
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->sellMode = $sellMode;
        $this->zones = new ArrayCollection();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function startsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function sellMode(): SellMode
    {
        return $this->sellMode;
    }

    /** @return Collection<int, ZoneModel> */
    public function zones(): Collection
    {
        return $this->zones;
    }

    public function addZone(ZoneModel $zone): void
    {
        if (!$this->zones->contains($zone)) {
            $this->zones->add($zone);
            $zone->setEvent($this);
        }
    }

    public function clearZones(): void
    {
        foreach ($this->zones as $zone) {
            $zone->setEvent(null);
        }

        $this->zones->clear();
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): void
    {
        $this->endsAt = $endsAt;
    }

    public function setSellMode(SellMode $sellMode): void
    {
        $this->sellMode = $sellMode;
    }
}
