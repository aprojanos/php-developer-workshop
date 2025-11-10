<?php

namespace App\Repository;

use SharedKernel\Contract\IntersectionRepositoryInterface;
use SharedKernel\Enum\IntersectionControlType;
use App\Model\Intersection;
use App\ValueObject\GeoLocation;

final class PdoIntersectionRepository implements IntersectionRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function save(Intersection $intersection): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO intersections (
                id,
                code,
                control_type,
                number_of_legs,
                has_cameras,
                aadt,
                spf_model_reference,
                geo_location,
                city,
                street
            ) VALUES (
                :id,
                :code,
                :control_type,
                :number_of_legs,
                :has_cameras,
                :aadt,
                :spf_model_reference,
                :geo_location,
                :city,
                :street
            )'
        );

        $stmt->execute($this->toParameters($intersection, includeId: true));
    }

    /** @return Intersection[] */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM intersections ORDER BY id');
        $rows = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        return array_map([$this, 'rowToIntersection'], $rows);
    }

    public function findById(int $id): ?Intersection
    {
        $stmt = $this->pdo->prepare('SELECT * FROM intersections WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->rowToIntersection($row) : null;
    }

    public function update(Intersection $intersection): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE intersections
             SET code = :code,
                 control_type = :control_type,
                 number_of_legs = :number_of_legs,
                 has_cameras = :has_cameras,
                 aadt = :aadt,
                 spf_model_reference = :spf_model_reference,
                 geo_location = :geo_location,
                 city = :city,
                 street = :street
             WHERE id = :id'
        );

        $stmt->execute($this->toParameters($intersection, includeId: true));
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM intersections WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToIntersection(array $row): Intersection
    {
        return new Intersection(
            id: (int)$row['id'],
            code: $row['code'] ?? null,
            controlType: IntersectionControlType::from($row['control_type']),
            numberOfLegs: (int)$row['number_of_legs'],
            hasCameras: $this->toBool($row['has_cameras'] ?? false),
            aadt: (int)$row['aadt'],
            spfModelReference: (string)$row['spf_model_reference'],
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
    private function toParameters(Intersection $intersection, bool $includeId = false): array
    {
        $params = [
            'code' => $intersection->code,
            'control_type' => $intersection->controlType->value,
            'number_of_legs' => $intersection->numberOfLegs,
            'has_cameras' => $this->boolToDatabaseValue($intersection->hasCameras),
            'aadt' => $intersection->aadt,
            'spf_model_reference' => $intersection->spfModelReference,
            'geo_location' => $intersection->geoLocation->wkt,
            'city' => $intersection->geoLocation->city,
            'street' => $intersection->geoLocation->street,
        ];

        if ($includeId) {
            $params['id'] = $intersection->id;
        }

        return $params;
    }

    private function boolToDatabaseValue(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function toBool(mixed $value): bool
    {
        $result = null;

        if (is_bool($value)) {
            $result = $value;
        } elseif (is_int($value)) {
            $result = $value !== 0;
        } elseif (is_string($value)) {
            $normalized = strtolower($value);
            if (in_array($normalized, ['1', 'true', 't', 'yes', 'y'], true)) {
                $result = true;
            } elseif (in_array($normalized, ['0', 'false', 'f', 'no', 'n'], true)) {
                $result = false;
            }
        }

        return $result ?? (bool)$value;
    }
}

