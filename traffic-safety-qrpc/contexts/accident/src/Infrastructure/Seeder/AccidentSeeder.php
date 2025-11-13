<?php

namespace AccidentContext\Infrastructure\Seeder;

use AccidentContext\Application\AccidentService;
use AccidentContext\Domain\Factory\AccidentFactory;
use AccidentContext\Infrastructure\Seeder\Exception\MissingAccidentLocationReferenceException;

final class AccidentSeeder
{
    private int $nextId;

    /**
     * @var array<int, array{id:int, latitude:float, longitude:float}>
     */
    private array $roadSegments = [];

    /**
     * @var array<int, array{id:int, latitude:float, longitude:float}>
     */
    private array $intersections = [];

    public function __construct(
        private readonly AccidentService $accidentService,
        private readonly \PDO $pdo,
    ) {
        $this->nextId = $this->fetchMaxId();
        $this->loadReferenceData();
    }

    public function run(int $pdoCount = 10, int $injuryCount = 20, bool $purgeExisting = true): void
    {
        if ($purgeExisting) {
            $this->purge();
            $this->nextId = 0;
        }

        if (($pdoCount > 0 || $injuryCount > 0) && $this->roadSegments === [] && $this->intersections === []) {
            throw new MissingAccidentLocationReferenceException('Cannot seed accidents without existing road segments or intersections.');
        }

        $this->seedAccidents('PDO', $pdoCount);
        $this->seedAccidents('Injury', $injuryCount);
    }

    public function purge(): void
    {
        $this->pdo->exec('DELETE FROM accidents');
    }

    private function fetchMaxId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM accidents');
        $result = $stmt !== false ? $stmt->fetchColumn() : 0;

        return (int)$result;
    }

    private function loadReferenceData(): void
    {
        $this->roadSegments = $this->fetchRoadSegments();
        $this->intersections = $this->fetchIntersections();
    }

    private function seedAccidents(string $type, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $payload = $this->generatePayload($type);
            $accident = AccidentFactory::create($payload);
            $this->accidentService->create($accident);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generatePayload(string $type): array
    {
        $occurredAt = (new \DateTimeImmutable('now'))
            ->sub(new \DateInterval('P' . random_int(0, 365) . 'D'))
            ->setTime(random_int(0, 23), random_int(0, 59));

        $locationChoice = $this->chooseLocation();

        $payload = [
            'id' => $this->generateId(),
            'occurredAt' => $occurredAt->format(DATE_ATOM),
            'type' => $type,
            'cost' => $type === 'PDO'
                ? random_int(5000, 80000) / 100
                : random_int(100000, 1000000) / 100,
            'latitude' => $locationChoice['latitude'],
            'longitude' => $locationChoice['longitude'],
        ];

        if ($locationChoice['type'] === 'road') {
            $payload['roadSegmentId'] = $locationChoice['id'];
            $payload['distanceFromStart'] = random_int(10, 1000) / 10;
        } else {
            $payload['intersectionId'] = $locationChoice['id'];
        }

        if ($type === 'Injury') {
            $payload['severity'] = self::randomSeverity();
            $payload['injuredPersonsCount'] = random_int(1, 5);
        }

        return $payload;
    }

    private static function randomSeverity(): string
    {
        $severities = ['minor', 'serious', 'severe', 'fatal'];

        return $severities[array_rand($severities)];
    }

    private function generateId(): int
    {
        $this->nextId++;

        return $this->nextId;
    }

    /**
     * @return array{id:int, latitude:float, longitude:float, type:'road'|'intersection'}
     */
    private function chooseLocation(): array
    {
        $useRoad = $this->shouldUseRoadLocation();
        $pool = $useRoad ? $this->roadSegments : $this->intersections;
        $location = $pool[array_rand($pool)];
        $location['type'] = $useRoad ? 'road' : 'intersection';

        return $location;
    }

    private function shouldUseRoadLocation(): bool
    {
        $hasRoad = $this->roadSegments !== [];
        $hasIntersection = $this->intersections !== [];

        if ($hasRoad && $hasIntersection) {
            return random_int(0, 1) === 1;
        }

        if ($hasRoad) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, array{id:int, latitude:float, longitude:float}>
     */
    private function fetchRoadSegments(): array
    {
        $stmt = $this->pdo->query('SELECT id, geo_location FROM road_segments');
        if ($stmt === false) {
            return [];
        }

        $segments = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($row['id'], $row['geo_location'])) {
                continue;
            }
            $point = $this->extractFirstPointFromLinestring((string)$row['geo_location']);
            if ($point === null) {
                continue;
            }
            $segments[] = [
                'id' => (int)$row['id'],
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
            ];
        }

        return $segments;
    }

    /**
     * @return array<int, array{id:int, latitude:float, longitude:float}>
     */
    private function fetchIntersections(): array
    {
        $stmt = $this->pdo->query('SELECT id, geo_location FROM intersections');
        if ($stmt === false) {
            return [];
        }

        $intersections = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($row['id'], $row['geo_location'])) {
                continue;
            }
            $point = $this->extractPoint((string)$row['geo_location']);
            if ($point === null) {
                continue;
            }
            $intersections[] = [
                'id' => (int)$row['id'],
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
            ];
        }

        return $intersections;
    }

    /**
     * @return array{latitude:float, longitude:float}|null
     */
    private function extractPoint(string $wkt): ?array
    {
        if (!preg_match('/POINT\s*\(\s*([+-]?\d+\.?\d*)\s+([+-]?\d+\.?\d*)\s*\)/i', $wkt, $matches)) {
            return null;
        }

        return [
            'longitude' => (float)$matches[1],
            'latitude' => (float)$matches[2],
        ];
    }

    /**
     * @return array{latitude:float, longitude:float}|null
     */
    private function extractFirstPointFromLinestring(string $wkt): ?array
    {
        if (!preg_match('/LINESTRING\s*\(([^)]+)\)/i', $wkt, $matches)) {
            return $this->extractPoint($wkt);
        }

        $points = preg_split('/\s*,\s*/', trim($matches[1]));
        $firstPoint = $points[0] ?? null;

        if ($firstPoint === null) {
            return null;
        }

        $coords = preg_split('/\s+/', trim($firstPoint));

        return ($coords && count($coords) >= 2)
            ? [
                'longitude' => (float)$coords[0],
                'latitude' => (float)$coords[1],
            ]
            : null;
    }
}

