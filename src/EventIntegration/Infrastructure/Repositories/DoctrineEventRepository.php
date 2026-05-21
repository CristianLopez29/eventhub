<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Repositories;

use App\EventIntegration\Domain\Entities\Event;
use App\EventIntegration\Domain\Entities\Zone;
use App\EventIntegration\Domain\Enums\SellMode;
use App\EventIntegration\Domain\Repositories\EventRepositoryInterface;
use App\EventIntegration\Domain\ValueObjects\EventId;
use App\EventIntegration\Domain\ValueObjects\Price;
use App\EventIntegration\Domain\ValueObjects\ZoneName;
use App\EventIntegration\Infrastructure\Persistence\EventModel;
use App\EventIntegration\Infrastructure\Persistence\ZoneModel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEventRepository implements EventRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function findById(EventId $eventId): ?Event
    {
        $model = $this->entityManager->find(EventModel::class, $eventId->value());

        if ($model === null) {
            return null;
        }

        return $this->reconstructEvent($model);
    }

    public function exists(EventId $eventId): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('1')
            ->from(EventModel::class, 'e')
            ->where('e.id = :id')
            ->setParameter('id', $eventId->value())
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    public function searchByDateRange(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from(EventModel::class, 'e')
            ->leftJoin('e.zones', 'z')
            ->addSelect('z')
            ->where('e.startsAt <= :endsAt')
            ->andWhere('e.endsAt >= :startsAt')
            ->setParameter('startsAt', $startsAt)
            ->setParameter('endsAt', $endsAt)
            ->orderBy('e.startsAt', 'ASC');

        /** @var EventModel[] $models */
        $models = $qb->getQuery()->getResult();

        $events = [];
        foreach ($models as $model) {
            $events[] = $this->reconstructEvent($model);
        }

        return $events;
    }

    public function save(Event $event): void
    {
        $existingModel = $this->entityManager->find(EventModel::class, $event->id()->value());

        if ($existingModel !== null) {
            $this->updateEventModel($existingModel, $event);
        } else {
            $model = $this->buildEventModel($event);
            $this->entityManager->persist($model);
        }

        $this->entityManager->flush();
    }

    private function buildEventModel(Event $event): EventModel
    {
        $model = new EventModel(
            $event->id()->value(),
            $event->title(),
            $event->startsAt(),
            $event->endsAt(),
            $event->sellMode()
        );

        foreach ($event->zones() as $zone) {
            $model->addZone(new ZoneModel(
                $zone->name()->value(),
                $zone->price()->cents(),
                $zone->capacity()
            ));
        }

        return $model;
    }

    private function updateEventModel(EventModel $model, Event $event): void
    {
        $model->setTitle($event->title());
        $model->setStartsAt($event->startsAt());
        $model->setEndsAt($event->endsAt());
        $model->setSellMode($event->sellMode());

        $model->clearZones();

        foreach ($event->zones() as $zone) {
            $model->addZone(new ZoneModel(
                $zone->name()->value(),
                $zone->price()->cents(),
                $zone->capacity()
            ));
        }
    }

    private function reconstructEvent(EventModel $model): Event
    {
        $event = new Event(
            new EventId($model->id()),
            $model->title(),
            $model->startsAt(),
            $model->endsAt(),
            $model->sellMode()
        );

        foreach ($model->zones() as $zoneModel) {
            $event->addZone(new Zone(
                new ZoneName($zoneModel->name()),
                new Price($zoneModel->priceCents()),
                $zoneModel->capacity()
            ));
        }

        return $event;
    }
}
