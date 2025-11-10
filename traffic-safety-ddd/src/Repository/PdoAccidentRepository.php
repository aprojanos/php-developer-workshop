<?php
namespace App\Repository;

use App\Model\AccidentBase;
use App\Factory\AccidentFactory;
use SharedKernel\Contract\AccidentRepositoryInterface;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\LocationType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;
use SharedKernel\DTO\AccidentLocationDTO;
use App\ValueObject\TimePeriod;

final class PdoAccidentRepository implements AccidentRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function save(AccidentBase $accident): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO accidents (id, occurred_at, location, severity, type, cost, road_segment_id, intersection_id, distance_from_start)
            VALUES (:id,:occurred_at,:location,:severity,:type,:cost,:road_segment_id,:intersection_id,:distance_from_start)');
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
            $result[] = AccidentFactory::create([
                'id' => (int)$r['id'],
                'occurredAt' => $r['occurred_at'],
                'location' => $location,
                'severity' => $r['severity'],
                'cost' => (float)$r['cost'],
                'roadSegmentId' => $r['road_segment_id'] !== null ? (int)$r['road_segment_id'] : null,
                'intersectionId' => $r['intersection_id'] !== null ? (int)$r['intersection_id'] : null,
                'distanceFromStart' => $r['distance_from_start'] !== null ? (float)$r['distance_from_start'] : null,
            ]);
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
        return AccidentFactory::create([
            'id' => (int)$r['id'],
            'occurredAt' => $r['occurred_at'],
            'location' => $location,
            'severity' => $r['severity'],
            'cost' => (float)$r['cost'],
            'roadSegmentId' => $r['road_segment_id'] !== null ? (int)$r['road_segment_id'] : null,
            'intersectionId' => $r['intersection_id'] !== null ? (int)$r['intersection_id'] : null,
            'distanceFromStart' => $r['distance_from_start'] !== null ? (float)$r['distance_from_start'] : null,
        ]);
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
                intersection_id = :intersection_id
                distance_from_start = :distance_from_start
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
            $result[] = AccidentFactory::create([
                'id' => (int)$r['id'],
                'occurredAt' => $r['occurred_at'],
                'location' => $accidentLocation,
                'severity' => $r['severity'],
                'cost' => (float)$r['cost'],
                'roadSegmentId' => $r['road_segment_id'] !== null ? (int)$r['road_segment_id'] : null,
                'intersectionId' => $r['intersection_id'] !== null ? (int)$r['intersection_id'] : null,
            ]);
        }
        return $result;
    }

    /** @return AccidentBase[] */
    public function search(
        ?TimePeriod $occurredAtInterval = null,
        ?AccidentLocationDTO $location = null,
        ?InjurySeverity $severity = null,
        ?AccidentType $type = null,
        ?CollisionType $collisionType = null,
        ?CauseFactor $causeFactor = null,
        ?WeatherCondition $weatherCondition = null,
        ?RoadCondition $roadCondition = null,
        ?VisibilityCondition $visibilityCondition = null,
        ?int $injuredPersonsCount = null
    ): array {
        $conditions = [];
        $params = [];

        // Date interval filter
        if ($occurredAtInterval !== null) {
            $conditions[] = 'occurred_at >= :start_date AND occurred_at <= :end_date';
            $params['start_date'] = $occurredAtInterval->startDate->format('c');
            $params['end_date'] = $occurredAtInterval->endDate->format('c');
        }

        // Location filter
        if ($location !== null) {
            $field = match ($location->locationType) {
                LocationType::ROADSEGMENT => 'road_segment_id',
                LocationType::INTERSECTION => 'intersection_id',
            };
            $conditions[] = "`{$field}` = :location_id";
            $params['location_id'] = $location->locationId;
        }

        // Severity filter
        if ($severity !== null) {
            $conditions[] = 'severity = :severity';
            $params['severity'] = $severity->value;
        }

        // Type filter (INJURY or PDO)
        if ($type !== null) {
            $conditions[] = 'type = :type';
            $params['type'] = $type->value;
        }

        // Collision type filter
        if ($collisionType !== null) {
            $conditions[] = 'collision_type = :collision_type';
            $params['collision_type'] = $collisionType->value;
        }

        // Cause factor filter
        if ($causeFactor !== null) {
            $conditions[] = 'cause_factor = :cause_factor';
            $params['cause_factor'] = $causeFactor->value;
        }

        // Weather conditions filter
        if ($weatherCondition !== null) {
            $conditions[] = 'weather_conditions = :weather_conditions';
            $params['weather_conditions'] = $weatherCondition->value;
        }

        // Road conditions filter
        if ($roadCondition !== null) {
            $conditions[] = 'road_conditions = :road_conditions';
            $params['road_conditions'] = $roadCondition->value;
        }

        // Visibility conditions filter
        if ($visibilityCondition !== null) {
            $conditions[] = 'visibility_conditions = :visibility_conditions';
            $params['visibility_conditions'] = $visibilityCondition->value;
        }

        // Injured persons count filter
        if ($injuredPersonsCount !== null) {
            $conditions[] = 'injured_persons_count = :injured_persons_count';
            $params['injured_persons_count'] = $injuredPersonsCount;
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
            $result[] = AccidentFactory::create([
                'id' => (int)$r['id'],
                'occurredAt' => $r['occurred_at'],
                'location' => $accidentLocation,
                'severity' => $r['severity'],
                'cost' => (float)$r['cost'],
                'roadSegmentId' => $r['road_segment_id'] !== null ? (int)$r['road_segment_id'] : null,
                'intersectionId' => $r['intersection_id'] !== null ? (int)$r['intersection_id'] : null,
            ]);
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
}
