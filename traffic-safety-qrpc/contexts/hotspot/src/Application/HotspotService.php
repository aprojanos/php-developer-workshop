<?php

namespace HotspotContext\Application;

use HotspotContext\Application\Port\AccidentProviderInterface;
use SharedKernel\Contract\HotspotRepositoryInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\HotspotCreatedEvent;
use SharedKernel\DTO\HotspotScreeningDTO;
use SharedKernel\DTO\HotspotSearchDTO;
use SharedKernel\Enum\HotspotStatus;
use SharedKernel\Enum\LocationType;
use SharedKernel\Model\AccidentBase;
use SharedKernel\Model\Hotspot;
use SharedKernel\Model\Intersection;
use SharedKernel\Model\RoadSegment;
use SharedKernel\ValueObject\TimePeriod;

final class HotspotService
{
    public function __construct(
        private HotspotRepositoryInterface $repository,
        private AccidentProviderInterface $accidentProvider,
        private ?LoggerInterface $logger = null,
        private ?EventBusInterface $eventBus = null,
    ) {}

    public function create(Hotspot $hotspot): void
    {
        $this->repository->save($hotspot);

        $this->logger?->info('Hotspot created', [
            'id' => $hotspot->id,
            'status' => $hotspot->status->value,
            'riskScore' => $hotspot->riskScore,
        ]);

        $this->eventBus?->dispatch(new HotspotCreatedEvent($hotspot));
    }

    public function findById(int $id): ?Hotspot
    {
        $hotspot = $this->repository->findById($id);

        if ($hotspot !== null) {
            $this->logger?->info('Hotspot retrieved', [
                'id' => $hotspot->id,
                'status' => $hotspot->status->value,
            ]);
        }

        return $hotspot;
    }

    public function update(Hotspot $hotspot): void
    {
        $existing = $this->repository->findById($hotspot->id);
        if ($existing === null) {
            throw new \InvalidArgumentException("Hotspot with ID {$hotspot->id} not found");
        }

        $this->repository->update($hotspot);

        $this->logger?->info('Hotspot updated', [
            'id' => $hotspot->id,
            'status' => $hotspot->status->value,
            'riskScore' => $hotspot->riskScore,
        ]);
    }

    public function delete(int $id): void
    {
        $hotspot = $this->repository->findById($id);
        if ($hotspot === null) {
            throw new \InvalidArgumentException("Hotspot with ID {$id} not found");
        }

        $this->repository->delete($id);

        $this->logger?->info('Hotspot deleted', [
            'id' => $id,
            'status' => $hotspot->status->value,
        ]);
    }

    /**
     * @return Hotspot[]
     */
    public function search(HotspotSearchDTO $searchDTO): array
    {
        $convertedStatus = $this->convertStatus($searchDTO->status);

        $hotspots = $this->repository->search(
            $searchDTO->period,
            $searchDTO->roadSegmentId,
            $searchDTO->intersectionId,
            $convertedStatus,
            $searchDTO->minRiskScore,
            $searchDTO->maxRiskScore,
            $searchDTO->minExpectedCrashes,
            $searchDTO->maxExpectedCrashes
        );

        usort($hotspots, fn(Hotspot $a, Hotspot $b) => $b->riskScore <=> $a->riskScore);

        $this->logger?->info('Hotspot search performed', [
            'criteria' => [
                'period' => $searchDTO->period?->startDate->format('c') . ' to ' . $searchDTO->period?->endDate->format('c'),
                'roadSegmentId' => $searchDTO->roadSegmentId,
                'intersectionId' => $searchDTO->intersectionId,
                'status' => $searchDTO->status,
                'minRiskScore' => $searchDTO->minRiskScore,
                'maxRiskScore' => $searchDTO->maxRiskScore,
            ],
            'resultsCount' => count($hotspots),
        ]);

        return $hotspots;
    }

    /**
     * @return array<int, array{locationId: int, score: float, accidentCount: int}>
     */
    public function screeningForHotspots(HotspotScreeningDTO $dto): array
    {
        $locationType = $dto->locationType;
        $allAccidents = $this->accidentProvider->all();

        $filteredAccidents = array_filter(
            $allAccidents,
            fn(AccidentBase $accident) => $this->isAccidentEligible($accident, $locationType, $dto->period)
        );

        $existingHotspots = $this->repository->all();
        [$existingRoadSegments, $existingIntersections] = $this->partitionExistingHotspots($existingHotspots);

        $accidentsByLocation = [];
        foreach ($filteredAccidents as $accident) {
            $locationId = $locationType === LocationType::ROADSEGMENT
                ? $accident->location->getRoadSegmentId()
                : $accident->location->getIntersectionId();

            if ($locationId === null) {
                continue;
            }

            $accidentsByLocation[$locationId][] = $accident;
        }

        $hotspots = $this->computeHotspotCandidates(
            $accidentsByLocation,
            $locationType,
            $dto->threshold,
            $existingRoadSegments,
            $existingIntersections
        );

        $this->logger?->info('Hotspot detection performed', [
            'threshold' => $dto->threshold,
            'type' => $locationType->value,
            'period' => $dto->period
                ? [
                    'start' => $dto->period->startDate->format('c'),
                    'end' => $dto->period->endDate->format('c'),
                ]
                : null,
            'totalAccidentsAnalyzed' => count($filteredAccidents),
            'locationsAnalyzed' => count($accidentsByLocation),
            'hotspotsDetected' => count($hotspots),
        ]);

        return $hotspots;
    }

    private function isAccidentEligible(AccidentBase $accident, LocationType $locationType, ?TimePeriod $period): bool
    {
        if ($accident->location->locationType !== $locationType) {
            return false;
        }

        if ($period !== null && !$period->contains($accident->occurredAt)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, array<int, AccidentBase>> $accidentsByLocation
     * @param array<int, true> $existingRoadSegments
     * @param array<int, true> $existingIntersections
     * @return array<int, array{locationId: int, score: float, accidentCount: int}>
     */
    private function computeHotspotCandidates(
        array $accidentsByLocation,
        LocationType $locationType,
        float $threshold,
        array $existingRoadSegments,
        array $existingIntersections
    ): array {
        $hotspots = [];

        foreach ($accidentsByLocation as $locationId => $accidents) {
            $score = $this->calculateTotalCost($accidents);

            if (
                ($locationType === LocationType::ROADSEGMENT && isset($existingRoadSegments[$locationId]))
                || ($locationType === LocationType::INTERSECTION && isset($existingIntersections[$locationId]))
            ) {
                continue;
            }

            if ($score > $threshold) {
                $hotspots[$locationId] = [
                    'locationId' => $locationId,
                    'score' => $score,
                    'accidentCount' => count($accidents),
                ];
            }
        }

        usort($hotspots, static fn($a, $b) => $b['score'] <=> $a['score']);

        return $hotspots;
    }

    /**
     * @param Hotspot[] $hotspots
     * @return array{array<int, true>, array<int, true>}
     */
    private function partitionExistingHotspots(array $hotspots): array
    {
        $roadSegments = [];
        $intersections = [];

        foreach ($hotspots as $hotspot) {
            if ($hotspot->location instanceof RoadSegment) {
                $roadSegments[$hotspot->location->id] = true;
            } elseif ($hotspot->location instanceof Intersection) {
                $intersections[$hotspot->location->id] = true;
            }
        }

        return [$roadSegments, $intersections];
    }

    private function calculateTotalCost(array $accidents): float
    {
        $sum = 0.0;

        foreach ($accidents as $accident) {
            $sum += $accident->cost;
        }

        return $sum;
    }

    private function convertStatus(mixed $value): ?HotspotStatus
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value) && $value::class === HotspotStatus::class) {
            return $value;
        }

        return is_string($value) ? HotspotStatus::tryFrom($value) : null;
    }
}

