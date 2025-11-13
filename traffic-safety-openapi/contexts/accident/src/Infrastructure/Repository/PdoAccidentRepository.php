<?php
namespace AccidentContext\Infrastructure\Repository;

use AccidentContext\Domain\Factory\AccidentFactory;
use SharedKernel\DTO\AccidentSearchCriteria;
use SharedKernel\Model\AccidentBase;
use SharedKernel\Contract\AccidentRepositoryInterface;
use SharedKernel\Enum\LocationType;
use SharedKernel\DTO\AccidentLocationDTO;

final class PdoAccidentRepository implements AccidentRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function save(AccidentBase $accident): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO accidents (
                id, occurred_at, location, severity, type, cost,
                road_segment_id, intersection_id, distance_from_start,
                collision_type, cause_factor, weather_conditions, road_conditions, visibility_conditions,
                injured_persons_count, location_description
            )
            VALUES (
                :id, :occurred_at, :location, :severity, :type, :cost,
                :road_segment_id, :intersection_id, :distance_from_start,
                :collision_type, :cause_factor, :weather_conditions, :road_conditions, :visibility_conditions,
                :injured_persons_count, :location_description
            )');
        $stmt->execute([
            'id' => $accident->id,
            'occurred_at' => $accident->occurredAt->format('c'),
            'location' => $this->locationToWkt($accident->location),
            'severity' => $accident->severity?->value,
            'type' => $accident->getType()->value,
            'cost' => $accident->cost,
            'road_segment_id' => $accident->location->getRoadSegmentId(),
            'intersection_id' => $accident->location->getIntersectionId(),
            'distance_from_start' => $accident->location->distanceFromStart,
            'collision_type' => $accident->collisionType?->value,
            'cause_factor' => $accident->causeFactor?->value,
            'weather_conditions' => $accident->weatherCondition?->value,
            'road_conditions' => $accident->roadCondition?->value,
            'visibility_conditions' => $accident->visibilityCondition?->value,
            'injured_persons_count' => $accident->injuredPersonsCount,
            'location_description' => $accident->locationDescription,
        ]);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM accidents');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $location = $this->wktToLocation(
                $r['location'],
                $r['road_segment_id'],
                $r['intersection_id'],
                $r['distance_from_start']
            );
            $result[] = $this->hydrateAccident($r, $location);
        }
        return $result;
    }

    public function findById(int $id): ?AccidentBase
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accidents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $location = $this->wktToLocation(
            $r['location'],
            $r['road_segment_id'],
            $r['intersection_id'],
            $r['distance_from_start']
        );
        return $this->hydrateAccident($r, $location);
    }

    public function update(AccidentBase $accident): void
    {
        $stmt = $this->pdo->prepare('UPDATE accidents
            SET occurred_at = :occurred_at,
                location = :location,
                severity = :severity,
                type = :type,
                cost = :cost,
                road_segment_id = :road_segment_id,
                intersection_id = :intersection_id,
                distance_from_start = :distance_from_start,
                collision_type = :collision_type,
                cause_factor = :cause_factor,
                weather_conditions = :weather_conditions,
                road_conditions = :road_conditions,
                visibility_conditions = :visibility_conditions,
                injured_persons_count = :injured_persons_count,
                location_description = :location_description,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute([
            'id' => $accident->id,
            'occurred_at' => $accident->occurredAt->format('c'),
            'location' => $this->locationToWkt($accident->location),
            'severity' => $accident->severity?->value,
            'type' => $accident->getType()->value,
            'cost' => $accident->cost,
            'road_segment_id' => $accident->location->getRoadSegmentId(),
            'intersection_id' => $accident->location->getIntersectionId(),
            'distance_from_start' => $accident->location->distanceFromStart,
            'collision_type' => $accident->collisionType?->value,
            'cause_factor' => $accident->causeFactor?->value,
            'weather_conditions' => $accident->weatherCondition?->value,
            'road_conditions' => $accident->roadCondition?->value,
            'visibility_conditions' => $accident->visibilityCondition?->value,
            'injured_persons_count' => $accident->injuredPersonsCount,
            'location_description' => $accident->locationDescription,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM accidents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @return AccidentBase[] */
    public function findByLocation(AccidentLocationDTO $location): array
    {
        $field = match ($location->locationType) {
            LocationType::ROADSEGMENT => 'road_segment_id',
            LocationType::INTERSECTION => 'intersection_id',
        };
        // Using backticks to safely quote the column name
        $stmt = $this->pdo->prepare("SELECT * FROM accidents WHERE `{$field}` = :location_id");
        $stmt->execute(['location_id' => $location->locationId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $accidentLocation = $this->wktToLocation(
                $r['location'],
                $r['road_segment_id'],
                $r['intersection_id'],
                $r['distance_from_start']
            );
            $result[] = $this->hydrateAccident($r, $accidentLocation);
        }
        return $result;
    }

    /** @return AccidentBase[] */
    public function search(AccidentSearchCriteria $criteria): array
    {
        $conditions = [];
        $params = [];

        // Date interval filter
        if ($criteria->occurredAtInterval !== null) {
            $conditions[] = 'occurred_at >= :start_date AND occurred_at <= :end_date';
            $params['start_date'] = $criteria->occurredAtInterval->startDate->format('c');
            $params['end_date'] = $criteria->occurredAtInterval->endDate->format('c');
        }

        // Location filter
        if ($criteria->location !== null) {
            $field = match ($criteria->location->locationType) {
                LocationType::ROADSEGMENT => 'road_segment_id',
                LocationType::INTERSECTION => 'intersection_id',
            };
            $conditions[] = "`{$field}` = :location_id";
            $params['location_id'] = $criteria->location->locationId;
        }

        // Severity filter
        if ($criteria->severity !== null) {
            $conditions[] = 'severity = :severity';
            $params['severity'] = $criteria->severity->value;
        }

        // Type filter (INJURY or PDO)
        if ($criteria->type !== null) {
            $conditions[] = 'type = :type';
            $params['type'] = $criteria->type->value;
        }

        // Collision type filter
        if ($criteria->collisionType !== null) {
            $conditions[] = 'collision_type = :collision_type';
            $params['collision_type'] = $criteria->collisionType->value;
        }

        // Cause factor filter
        if ($criteria->causeFactor !== null) {
            $conditions[] = 'cause_factor = :cause_factor';
            $params['cause_factor'] = $criteria->causeFactor->value;
        }

        // Weather conditions filter
        if ($criteria->weatherCondition !== null) {
            $conditions[] = 'weather_conditions = :weather_conditions';
            $params['weather_conditions'] = $criteria->weatherCondition->value;
        }

        // Road conditions filter
        if ($criteria->roadCondition !== null) {
            $conditions[] = 'road_conditions = :road_conditions';
            $params['road_conditions'] = $criteria->roadCondition->value;
        }

        // Visibility conditions filter
        if ($criteria->visibilityCondition !== null) {
            $conditions[] = 'visibility_conditions = :visibility_conditions';
            $params['visibility_conditions'] = $criteria->visibilityCondition->value;
        }

        // Injured persons count filter
        if ($criteria->injuredPersonsCount !== null) {
            $conditions[] = 'injured_persons_count = :injured_persons_count';
            $params['injured_persons_count'] = $criteria->injuredPersonsCount;
        }

        // Build SQL query
        $sql = 'SELECT * FROM accidents';
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $accidentLocation = $this->wktToLocation(
                $r['location'],
                $r['road_segment_id'],
                $r['intersection_id'],
                $r['distance_from_start']
            );
            $result[] = $this->hydrateAccident($r, $accidentLocation);
        }
        return $result;
    }

    /**
     * Convert AccidentLocationDTO to WKT format.
     * WKT format: POINT(longitude latitude)
     */
    private function locationToWkt(AccidentLocationDTO $location): string
    {
        return sprintf('POINT(%f %f)', $location->longitude, $location->latitude);
    }

    /**
     * Convert WKT format to AccidentLocationDTO.
     * WKT format: POINT(longitude latitude)
     */
    private function wktToLocation(string $wkt, ?string $roadSegmentId, ?string $intersectionId, ?float $distanceFromStart = null): AccidentLocationDTO
    {
        // Parse WKT POINT format: POINT(longitude latitude)
        if (!preg_match('/POINT\s*\(\s*([+-]?\d+\.?\d*)\s+([+-]?\d+\.?\d*)\s*\)/i', $wkt, $matches)) {
            throw new \InvalidArgumentException("Invalid WKT format: {$wkt}");
        }

        $longitude = (float)$matches[1];
        $latitude = (float)$matches[2];

        // Determine location type and ID from database fields
        $locationType = $roadSegmentId !== null ? LocationType::ROADSEGMENT : LocationType::INTERSECTION;
        $locationId = $roadSegmentId !== null ? (int)$roadSegmentId : (int)$intersectionId;

        return new AccidentLocationDTO(
            locationType: $locationType,
            locationId: $locationId,
            latitude: $latitude,
            longitude: $longitude,
            distanceFromStart: $distanceFromStart
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateAccident(array $row, AccidentLocationDTO $location): AccidentBase
    {
        return AccidentFactory::create([
            'id' => (int)$row['id'],
            'occurredAt' => $row['occurred_at'],
            'location' => $location,
            'severity' => $row['severity'],
            'cost' => (float)$row['cost'],
            'roadSegmentId' => $row['road_segment_id'] !== null ? (int)$row['road_segment_id'] : null,
            'intersectionId' => $row['intersection_id'] !== null ? (int)$row['intersection_id'] : null,
            'distanceFromStart' => $row['distance_from_start'] !== null ? (float)$row['distance_from_start'] : null,
            'collisionType' => $row['collision_type'],
            'causeFactor' => $row['cause_factor'],
            'weatherCondition' => $row['weather_conditions'],
            'roadCondition' => $row['road_conditions'],
            'visibilityCondition' => $row['visibility_conditions'],
            'injuredPersonsCount' => isset($row['injured_persons_count']) ? (int)$row['injured_persons_count'] : null,
            'locationDescription' => $row['location_description'] ?? null,
        ]);
    }
}
