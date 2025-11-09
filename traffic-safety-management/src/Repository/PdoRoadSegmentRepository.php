<?php

namespace App\Repository;

use App\Contract\RoadSegmentRepositoryInterface;
use App\Enum\FunctionalClass;
use App\Model\RoadSegment;
use App\ValueObject\GeoLocation;

final class PdoRoadSegmentRepository implements RoadSegmentRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function save(RoadSegment $roadSegment): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO road_segments (
                id,
                code,
                length_km,
                lane_count,
                functional_class,
                speed_limit_kmh,
                aadt,
                geo_location,
                city,
                street
            ) VALUES (
                :id,
                :code,
                :length_km,
                :lane_count,
                :functional_class,
                :speed_limit_kmh,
                :aadt,
                :geo_location,
                :city,
                :street
            )'
        );

        $stmt->execute($this->toParameters($roadSegment, includeId: true));
    }

    /** @return RoadSegment[] */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM road_segments ORDER BY id');
        $rows = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        return array_map([$this, 'rowToRoadSegment'], $rows);
    }

    public function findById(int $id): ?RoadSegment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM road_segments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->rowToRoadSegment($row) : null;
    }

    public function update(RoadSegment $roadSegment): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE road_segments
             SET code = :code,
                 length_km = :length_km,
                 lane_count = :lane_count,
                 functional_class = :functional_class,
                 speed_limit_kmh = :speed_limit_kmh,
                 aadt = :aadt,
                 geo_location = :geo_location,
                 city = :city,
                 street = :street
             WHERE id = :id'
        );

        $stmt->execute($this->toParameters($roadSegment, includeId: true));
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM road_segments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToRoadSegment(array $row): RoadSegment
    {
        return new RoadSegment(
            id: (int)$row['id'],
            code: $row['code'] ?? null,
            lengthKm: (float)$row['length_km'],
            laneCount: (int)$row['lane_count'],
            functionalClass: FunctionalClass::from($row['functional_class']),
            speedLimitKmh: (int)$row['speed_limit_kmh'],
            aadt: (int)$row['aadt'],
            geoLocation: new GeoLocation(
                wkt: (string)$row['geo_location'],
                city: $row['city'] ?? null,
                street: $row['street'] ?? null,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toParameters(RoadSegment $roadSegment, bool $includeId = false): array
    {
        $params = [
            'code' => $roadSegment->code,
            'length_km' => $roadSegment->lengthKm,
            'lane_count' => $roadSegment->laneCount,
            'functional_class' => $roadSegment->functionalClass->value,
            'speed_limit_kmh' => $roadSegment->speedLimitKmh,
            'aadt' => $roadSegment->aadt,
            'geo_location' => $roadSegment->geoLocation->wkt,
            'city' => $roadSegment->geoLocation->city,
            'street' => $roadSegment->geoLocation->street,
        ];

        if ($includeId) {
            $params['id'] = $roadSegment->id;
        }

        return $params;
    }
}

