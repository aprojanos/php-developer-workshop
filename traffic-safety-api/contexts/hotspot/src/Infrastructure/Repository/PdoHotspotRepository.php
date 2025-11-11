<?php

namespace HotspotContext\Infrastructure\Repository;

use HotspotContext\Domain\Factory\HotspotFactory;
use SharedKernel\Contract\HotspotRepositoryInterface;
use SharedKernel\Enum\FunctionalClass;
use SharedKernel\Enum\HotspotStatus;
use SharedKernel\Enum\IntersectionControlType;
use SharedKernel\Model\Hotspot;
use SharedKernel\Model\Intersection;
use SharedKernel\Model\RoadSegment;
use SharedKernel\ValueObject\GeoLocation;
use SharedKernel\ValueObject\ObservedCrashes;
use SharedKernel\ValueObject\TimePeriod;

final class PdoHotspotRepository implements HotspotRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function save(Hotspot $hotspot): void
    {
        $roadSegmentId = $hotspot->location instanceof RoadSegment ? $hotspot->location->id : null;
        $intersectionId = $hotspot->location instanceof Intersection ? $hotspot->location->id : null;

        $stmt = $this->pdo->prepare('INSERT INTO hotspots (
            id, road_segment_id, intersection_id, period_start, period_end,
            observed_crashes, expected_crashes, risk_score, status, screening_parameters
        ) VALUES (
            :id, :road_segment_id, :intersection_id, :period_start, :period_end,
            :observed_crashes, :expected_crashes, :risk_score, :status, :screening_parameters
        )');

        $stmt->execute([
            'id' => $hotspot->id,
            'road_segment_id' => $roadSegmentId,
            'intersection_id' => $intersectionId,
            'period_start' => $hotspot->period->startDate->format('c'),
            'period_end' => $hotspot->period->endDate->format('c'),
            'observed_crashes' => $this->observedCrashesToJson($hotspot->observedCrashes),
            'expected_crashes' => $hotspot->expectedCrashes,
            'risk_score' => $hotspot->riskScore,
            'status' => $hotspot->status->value,
            'screening_parameters' => $hotspot->screeningParameters !== null ? json_encode($hotspot->screeningParameters) : null,
        ]);
    }

    /**
     * @return Hotspot[]
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM hotspots');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'rowToHotspot'], $rows);
    }

    public function findById(int $id): ?Hotspot
    {
        $stmt = $this->pdo->prepare('SELECT * FROM hotspots WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->rowToHotspot($row) : null;
    }

    public function update(Hotspot $hotspot): void
    {
        $roadSegmentId = $hotspot->location instanceof RoadSegment ? $hotspot->location->id : null;
        $intersectionId = $hotspot->location instanceof Intersection ? $hotspot->location->id : null;

        $stmt = $this->pdo->prepare('UPDATE hotspots
            SET road_segment_id = :road_segment_id,
                intersection_id = :intersection_id,
                period_start = :period_start,
                period_end = :period_end,
                observed_crashes = :observed_crashes,
                expected_crashes = :expected_crashes,
                risk_score = :risk_score,
                status = :status,
                screening_parameters = :screening_parameters
            WHERE id = :id');

        $stmt->execute([
            'id' => $hotspot->id,
            'road_segment_id' => $roadSegmentId,
            'intersection_id' => $intersectionId,
            'period_start' => $hotspot->period->startDate->format('c'),
            'period_end' => $hotspot->period->endDate->format('c'),
            'observed_crashes' => $this->observedCrashesToJson($hotspot->observedCrashes),
            'expected_crashes' => $hotspot->expectedCrashes,
            'risk_score' => $hotspot->riskScore,
            'status' => $hotspot->status->value,
            'screening_parameters' => $hotspot->screeningParameters !== null ? json_encode($hotspot->screeningParameters) : null,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM hotspots WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @return Hotspot[]
     */
    public function search(
        ?TimePeriod $period = null,
        ?int $roadSegmentId = null,
        ?int $intersectionId = null,
        ?HotspotStatus $status = null,
        ?float $minRiskScore = null,
        ?float $maxRiskScore = null,
        ?float $minExpectedCrashes = null,
        ?float $maxExpectedCrashes = null
    ): array {
        $conditions = [];
        $params = [];

        if ($period !== null) {
            $conditions[] = '(period_start <= :period_end AND period_end >= :period_start)';
            $params['period_start'] = $period->startDate->format('c');
            $params['period_end'] = $period->endDate->format('c');
        }

        if ($roadSegmentId !== null) {
            $conditions[] = 'road_segment_id = :road_segment_id';
            $params['road_segment_id'] = $roadSegmentId;
        }

        if ($intersectionId !== null) {
            $conditions[] = 'intersection_id = :intersection_id';
            $params['intersection_id'] = $intersectionId;
        }

        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status->value;
        }

        if ($minRiskScore !== null) {
            $conditions[] = 'risk_score >= :min_risk_score';
            $params['min_risk_score'] = $minRiskScore;
        }

        if ($maxRiskScore !== null) {
            $conditions[] = 'risk_score <= :max_risk_score';
            $params['max_risk_score'] = $maxRiskScore;
        }

        if ($minExpectedCrashes !== null) {
            $conditions[] = 'expected_crashes >= :min_expected_crashes';
            $params['min_expected_crashes'] = $minExpectedCrashes;
        }

        if ($maxExpectedCrashes !== null) {
            $conditions[] = 'expected_crashes <= :max_expected_crashes';
            $params['max_expected_crashes'] = $maxExpectedCrashes;
        }

        $sql = 'SELECT * FROM hotspots';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY risk_score DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'rowToHotspot'], $rows);
    }

    private function rowToHotspot(array $row): Hotspot
    {
        $location = $this->loadLocation(
            $row['road_segment_id'],
            $row['intersection_id']
        );

        $observedCrashesData = json_decode($row['observed_crashes'] ?? '{}', true) ?: [];
        $screeningParameters = !empty($row['screening_parameters'])
            ? json_decode($row['screening_parameters'], true)
            : null;

        return HotspotFactory::create([
            'id' => (int)$row['id'],
            'location' => $location,
            'period_start' => $row['period_start'],
            'period_end' => $row['period_end'],
            'observed_crashes' => $observedCrashesData,
            'expected_crashes' => (float)$row['expected_crashes'],
            'risk_score' => (float)$row['risk_score'],
            'status' => $row['status'],
            'screening_parameters' => $screeningParameters,
        ]);
    }

    private function loadLocation(?string $roadSegmentId, ?string $intersectionId): RoadSegment|Intersection
    {
        if ($roadSegmentId !== null) {
            return $this->loadRoadSegment((int)$roadSegmentId);
        }
        if ($intersectionId !== null) {
            return $this->loadIntersection((int)$intersectionId);
        }

        throw new \RuntimeException('Hotspot must have either road_segment_id or intersection_id');
    }

    private function loadRoadSegment(int $id): RoadSegment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM road_segments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException("RoadSegment with id {$id} not found");
        }

        return new RoadSegment(
            id: (int)$row['id'],
            code: $row['code'] ?? null,
            lengthKm: (float)$row['length_km'],
            laneCount: (int)$row['lane_count'],
            functionalClass: FunctionalClass::from($row['functional_class']),
            speedLimitKmh: (int)$row['speed_limit_kmh'],
            aadt: (int)$row['aadt'],
            geoLocation: new GeoLocation(
                wkt: $row['geo_location'] ?? $row['geo_location_wkt'] ?? '',
                city: $row['city'] ?? null,
                street: $row['street'] ?? null
            )
        );
    }

    private function loadIntersection(int $id): Intersection
    {
        $stmt = $this->pdo->prepare('SELECT * FROM intersections WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException("Intersection with id {$id} not found");
        }

        return new Intersection(
            id: (int)$row['id'],
            code: $row['code'] ?? null,
            controlType: IntersectionControlType::from($row['control_type']),
            numberOfLegs: (int)$row['number_of_legs'],
            hasCameras: (bool)$row['has_cameras'],
            aadt: (int)$row['aadt'],
            spfModelReference: $row['spf_model_reference'],
            geoLocation: new GeoLocation(
                wkt: $row['geo_location'] ?? $row['geo_location_wkt'] ?? '',
                city: $row['city'] ?? null,
                street: $row['street'] ?? null
            )
        );
    }

    private function observedCrashesToJson(ObservedCrashes $observedCrashes): string
    {
        $data = [];
        foreach ($observedCrashes->toArray() as $type => $count) {
            $data[$type] = $count;
        }

        return json_encode($data);
    }
}

