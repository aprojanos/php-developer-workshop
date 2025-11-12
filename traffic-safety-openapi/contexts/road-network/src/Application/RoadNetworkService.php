<?php

namespace RoadNetworkContext\Application;

use SharedKernel\Contract\IntersectionRepositoryInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\RoadSegmentRepositoryInterface;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\IntersectionCreatedEvent;
use SharedKernel\Domain\Event\IntersectionDeletedEvent;
use SharedKernel\Domain\Event\IntersectionUpdatedEvent;
use SharedKernel\Domain\Event\RoadSegmentCreatedEvent;
use SharedKernel\Domain\Event\RoadSegmentDeletedEvent;
use SharedKernel\Domain\Event\RoadSegmentUpdatedEvent;
use SharedKernel\Model\Intersection;
use SharedKernel\Model\RoadSegment;

final class RoadNetworkService
{
    public function __construct(
        private IntersectionRepositoryInterface $intersectionRepository,
        private RoadSegmentRepositoryInterface $roadSegmentRepository,
        private ?LoggerInterface $logger = null,
        private ?EventBusInterface $eventBus = null,
    ) {}

    /**
     * @return Intersection[]
     */
    public function listIntersections(): array
    {
        $intersections = $this->intersectionRepository->all();

        $this->logger?->info('Intersections retrieved', [
            'count' => count($intersections),
        ]);

        return $intersections;
    }

    public function getIntersection(int $id): ?Intersection
    {
        $intersection = $this->intersectionRepository->findById($id);

        if ($intersection !== null) {
            $this->logger?->info('Intersection retrieved', [
                'id' => $intersection->id,
                'controlType' => $intersection->controlType->value,
            ]);
        }

        return $intersection;
    }

    public function createIntersection(Intersection $intersection): void
    {
        $this->intersectionRepository->save($intersection);

        $this->logger?->info('Intersection created', [
            'id' => $intersection->id,
            'controlType' => $intersection->controlType->value,
            'numberOfLegs' => $intersection->numberOfLegs,
            'hasCameras' => $intersection->hasCameras,
        ]);

        $this->eventBus?->dispatch(new IntersectionCreatedEvent($intersection));
    }

    public function updateIntersection(Intersection $intersection): void
    {
        $existing = $this->intersectionRepository->findById($intersection->id);
        if ($existing === null) {
            throw new \InvalidArgumentException("Intersection with ID {$intersection->id} not found");
        }

        $this->intersectionRepository->update($intersection);

        $this->logger?->info('Intersection updated', [
            'id' => $intersection->id,
            'controlType' => $intersection->controlType->value,
            'numberOfLegs' => $intersection->numberOfLegs,
            'hasCameras' => $intersection->hasCameras,
        ]);

        $this->eventBus?->dispatch(new IntersectionUpdatedEvent($intersection));
    }

    public function deleteIntersection(int $id): void
    {
        $intersection = $this->intersectionRepository->findById($id);
        if ($intersection === null) {
            throw new \InvalidArgumentException("Intersection with ID {$id} not found");
        }

        $this->intersectionRepository->delete($id);

        $this->logger?->info('Intersection deleted', [
            'id' => $id,
            'controlType' => $intersection->controlType->value,
        ]);

        $this->eventBus?->dispatch(new IntersectionDeletedEvent($id));
    }

    /**
     * @return RoadSegment[]
     */
    public function listRoadSegments(): array
    {
        $roadSegments = $this->roadSegmentRepository->all();

        $this->logger?->info('Road segments retrieved', [
            'count' => count($roadSegments),
        ]);

        return $roadSegments;
    }

    public function getRoadSegment(int $id): ?RoadSegment
    {
        $roadSegment = $this->roadSegmentRepository->findById($id);

        if ($roadSegment !== null) {
            $this->logger?->info('Road segment retrieved', [
                'id' => $roadSegment->id,
                'lengthKm' => $roadSegment->lengthKm,
                'laneCount' => $roadSegment->laneCount,
            ]);
        }

        return $roadSegment;
    }

    public function createRoadSegment(RoadSegment $roadSegment): void
    {
        $this->roadSegmentRepository->save($roadSegment);

        $this->logger?->info('Road segment created', [
            'id' => $roadSegment->id,
            'lengthKm' => $roadSegment->lengthKm,
            'laneCount' => $roadSegment->laneCount,
            'functionalClass' => $roadSegment->functionalClass->value,
        ]);

        $this->eventBus?->dispatch(new RoadSegmentCreatedEvent($roadSegment));
    }

    public function updateRoadSegment(RoadSegment $roadSegment): void
    {
        $existing = $this->roadSegmentRepository->findById($roadSegment->id);
        if ($existing === null) {
            throw new \InvalidArgumentException("Road segment with ID {$roadSegment->id} not found");
        }

        $this->roadSegmentRepository->update($roadSegment);

        $this->logger?->info('Road segment updated', [
            'id' => $roadSegment->id,
            'lengthKm' => $roadSegment->lengthKm,
            'laneCount' => $roadSegment->laneCount,
            'functionalClass' => $roadSegment->functionalClass->value,
        ]);

        $this->eventBus?->dispatch(new RoadSegmentUpdatedEvent($roadSegment));
    }

    public function deleteRoadSegment(int $id): void
    {
        $roadSegment = $this->roadSegmentRepository->findById($id);
        if ($roadSegment === null) {
            throw new \InvalidArgumentException("Road segment with ID {$id} not found");
        }

        $this->roadSegmentRepository->delete($id);

        $this->logger?->info('Road segment deleted', [
            'id' => $id,
            'lengthKm' => $roadSegment->lengthKm,
            'laneCount' => $roadSegment->laneCount,
        ]);

        $this->eventBus?->dispatch(new RoadSegmentDeletedEvent($id));
    }
}

