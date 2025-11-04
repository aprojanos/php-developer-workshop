<?php
namespace App\Service;

use App\Contract\HotspotRepositoryInterface;
use App\Contract\LoggerInterface;
use App\Model\Hotspot;
use App\DTO\HotspotSearchDTO;
use App\Enum\HotspotStatus;
use App\Service\AccidentService;
use App\Enum\LocationType;
use App\Model\AccidentBase;

final class HotspotService
{
    public function __construct(
        private HotspotRepositoryInterface $repository,
        private AccidentService $accidentService,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Create a new hotspot
     *
     * @param Hotspot $hotspot The hotspot to create
     * @return void
     */
    public function create(Hotspot $hotspot): void
    {
        $this->repository->save($hotspot);

        $this->logger?->info('Hotspot created', [
            'id' => $hotspot->id,
            'status' => $hotspot->status->value,
            'riskScore' => $hotspot->riskScore,
        ]);
    }

    /**
     * Find a hotspot by its ID
     *
     * @param int $id The hotspot ID
     * @return Hotspot|null The hotspot or null if not found
     */
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

    /**
     * Update an existing hotspot
     *
     * @param Hotspot $hotspot The hotspot to update
     * @return void
     */
    public function update(Hotspot $hotspot): void
    {
        // Check if hotspot exists
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

    /**
     * Delete a hotspot by its ID
     *
     * @param int $id The hotspot ID
     * @return void
     */
    public function delete(int $id): void
    {
        // Check if hotspot exists
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
     * Search for hotspots based on multiple criteria
     * Results are sorted by risk_score in descending order (highest risk first)
     *
     * @param HotspotSearchDTO $searchDTO Search criteria DTO
     * @return Hotspot[] Array of matching hotspots sorted by risk_score descending
     */
    public function search(HotspotSearchDTO $searchDTO): array
    {
        // Convert status string to enum if provided
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

        // Repository already sorts by risk_score DESC, but ensure it here as well for safety
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
     * Detect hotspots based on accident cost threshold
     * Calculates the sum of estimated costs for all accidents on each road element
     * and returns elements with scores above the threshold
     *
     * @param float $threshold Minimum score (total cost) to be considered a hotspot
     * @param LocationType|string $type Location type ('roadsegment' or 'intersection')
     * @return array<int, array{locationId: int, score: float, accidentCount: int}> Array of detected hotspots with location ID, score, and accident count
     */
    public function detectHotspot(float $threshold, LocationType|string $type): array
    {
        // Convert string to enum if needed
        $locationType = $type instanceof LocationType ? $type : LocationType::from(strtolower($type));

        // Get all accidents from the accident service
        $allAccidents = $this->accidentService->all();

        // Filter accidents by location type
        $filteredAccidents = array_filter(
            $allAccidents,
            fn(AccidentBase $accident) => $accident->location->locationType === $locationType
        );

        // Group accidents by location ID
        $accidentsByLocation = [];
        foreach ($filteredAccidents as $accident) {
            $locationId = $locationType === LocationType::ROADSEGMENT
                ? $accident->location->getRoadSegmentId()
                : $accident->location->getIntersectionId();

            if ($locationId === null) {
                continue;
            }

            if (!isset($accidentsByLocation[$locationId])) {
                $accidentsByLocation[$locationId] = [];
            }
            $accidentsByLocation[$locationId][] = $accident;
        }

        // Calculate score (total cost) for each location and filter by threshold
        $hotspots = [];
        foreach ($accidentsByLocation as $locationId => $accidents) {
            $score = $this->accidentService->calculateTotalCost($accidents);
            
            if ($score > $threshold) {
                $hotspots[$locationId] = [
                    'locationId' => $locationId,
                    'score' => $score,
                    'accidentCount' => count($accidents),
                ];
            }
        }

        // Sort by score descending (highest scores first)
        usort($hotspots, fn($a, $b) => $b['score'] <=> $a['score']);

        $this->logger?->info('Hotspot detection performed', [
            'threshold' => $threshold,
            'type' => $locationType->value,
            'totalAccidentsAnalyzed' => count($filteredAccidents),
            'locationsAnalyzed' => count($accidentsByLocation),
            'hotspotsDetected' => count($hotspots),
        ]);

        return $hotspots;
    }

    /**
     * Convert a string or enum to a HotspotStatus enum instance
     *
     * @param string|\BackedEnum|null $value The value to convert
     * @return HotspotStatus|null The enum instance or null
     */
    private function convertStatus(mixed $value): ?HotspotStatus
    {
        if ($value === null) {
            return null;
        }

        // Check if value is already the correct enum instance
        if (is_object($value) && $value::class === HotspotStatus::class) {
            return $value;
        }

        // Convert string to enum
        return is_string($value) ? HotspotStatus::tryFrom($value) : null;
    }
}
