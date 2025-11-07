<?php

namespace App\Seeder;

use App\Enum\AccidentType;
use App\Enum\FunctionalClass;
use App\Enum\HotspotStatus;
use App\Enum\IntersectionControlType;
use App\Factory\HotspotFactory;
use App\Model\Intersection;
use App\Model\RoadSegment;
use App\Seeder\Exception\MissingHotspotLocationReferenceException;
use App\Service\HotspotService;
use App\ValueObject\GeoLocation;
use Random\Randomizer;

final class HotspotSeeder
{
    private int $nextId;

    /** @var array<int, RoadSegment> */
    private array $roadSegments = [];

    /** @var array<int, Intersection> */
    private array $intersections = [];

    private float $intersectionShare = 0.5;

    private readonly Randomizer $randomizer;

    public function __construct(
        private readonly HotspotService $hotspotService,
        private readonly \PDO $pdo,
    ) {
        $this->randomizer = new Randomizer();
        $this->nextId = $this->fetchMaxId();
        $this->roadSegments = $this->fetchRoadSegments();
        $this->intersections = $this->fetchIntersections();
    }

    public function run(int $count = 10, bool $purgeExisting = true, float $intersectionShare = 0.5): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('HotspotSeeder requires at least one hotspot to seed.');
        }

        $this->intersectionShare = max(0.0, min(1.0, $intersectionShare));

        if ($purgeExisting) {
            $this->purge();
            $this->nextId = 0;
        }

        if (empty($this->roadSegments) && empty($this->intersections)) {
            throw new MissingHotspotLocationReferenceException('Cannot seed hotspots without road segments or intersections.');
        }

        $this->seedHotspots($count);
    }

    private function purge(): void
    {
        $this->pdo->exec('DELETE FROM hotspots');
    }

    private function fetchMaxId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM hotspots');
        $result = $stmt !== false ? $stmt->fetchColumn() : 0;
        return (int)$result;
    }

    private function seedHotspots(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $payload = $this->generateHotspotPayload();
            $hotspot = HotspotFactory::create($payload);
            $this->hotspotService->create($hotspot);
        }
    }

    private function generateHotspotPayload(): array
    {
        $location = $this->chooseLocation();
        [$periodStart, $periodEnd] = $this->randomPeriod();
        $observed = $this->randomObservedCrashes();
        $expected = $this->computeExpectedCrashes($observed);
        $riskScore = $this->computeRiskScore($observed, $expected);

        return [
            'id' => $this->generateId(),
            'location' => $location,
            'period_start' => $periodStart->format(DATE_ATOM),
            'period_end' => $periodEnd->format(DATE_ATOM),
            'observed_crashes' => $observed,
            'expected_crashes' => $expected,
            'risk_score' => $riskScore,
            'status' => $this->randomStatus()->value,
            'screening_parameters' => $this->randomScreeningParameters($location, $riskScore),
        ];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function randomPeriod(): array
    {
        $end = (new \DateTimeImmutable('now'))
            ->sub(new \DateInterval('P' . $this->randomizer->getInt(0, 60) . 'D'));
        $start = $end->sub(new \DateInterval('P' . $this->randomizer->getInt(90, 365) . 'D'));

        return [$start, $end];
    }

    private function chooseLocation(): RoadSegment|Intersection
    {
        $hasRoad = !empty($this->roadSegments);
        $hasIntersection = !empty($this->intersections);

        if ($hasRoad && $hasIntersection) {
            $useIntersection = $this->randomizer->getFloat(0.0, 1.0) <= $this->intersectionShare;
        } else {
            $useIntersection = $hasIntersection;
        }

        if ($useIntersection) {
            return $this->intersections[array_rand($this->intersections)];
        }

        return $this->roadSegments[array_rand($this->roadSegments)];
    }

    /**
     * @return array<string, int>
     */
    private function randomObservedCrashes(): array
    {
        $pdoCrashes = $this->randomizer->getInt(0, 20);
        $injuryCrashes = $this->randomizer->getInt(0, 12);

        if ($pdoCrashes === 0 && $injuryCrashes === 0) {
            $pdoCrashes = 1;
        }

        return [
            AccidentType::PDO->value => $pdoCrashes,
            AccidentType::INJURY->value => $injuryCrashes,
        ];
    }

    /**
     * @param array<string, int> $observed
     */
    private function computeExpectedCrashes(array $observed): float
    {
        $totalObserved = array_sum($observed);
        $baseline = max(1, $totalObserved);
        $ratio = $this->randomizer->getFloat(0.55, 0.9);
        return round($baseline * $ratio, 2);
    }

    /**
     * @param array<string, int> $observed
     */
    private function computeRiskScore(array $observed, float $expected): float
    {
        $totalObserved = array_sum($observed);
        if ($expected <= 0.0) {
            return round($totalObserved * $this->randomizer->getFloat(0.8, 1.5), 2);
        }

        $ratio = $totalObserved / $expected;
        return round($ratio * $this->randomizer->getFloat(0.9, 1.4), 2);
    }

    private function randomStatus(): HotspotStatus
    {
        $cases = HotspotStatus::cases();
        return $cases[array_rand($cases)];
    }

    private function randomScreeningParameters(RoadSegment|Intersection $location, float $riskScore): array
    {
        $methods = ['EB', 'Sliding Window', 'Kernel Density'];
        $method = $methods[array_rand($methods)];

        return [
            'method' => $method,
            'threshold' => round($this->randomizer->getFloat(0.7, 1.6), 2),
            'riskScore' => $riskScore,
            'locationId' => $location->id,
            'generatedAt' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<int, RoadSegment>
     */
    private function fetchRoadSegments(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM road_segments');
        if ($stmt === false) {
            return [];
        }

        $segments = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $segment = $this->createRoadSegmentFromRow($row);
            if ($segment !== null) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createRoadSegmentFromRow(array $row): ?RoadSegment
    {
        if (!isset($row['id'], $row['length_km'], $row['lane_count'], $row['functional_class'], $row['speed_limit_kmh'], $row['aadt'])) {
            return null;
        }

        try {
            $functionalClass = FunctionalClass::from($row['functional_class']);
        } catch (\Throwable) {
            return null;
        }

        return new RoadSegment(
            id: (int)$row['id'],
            code: $row['code'] ?? null,
            lengthKm: (float)$row['length_km'],
            laneCount: (int)$row['lane_count'],
            functionalClass: $functionalClass,
            speedLimitKmh: (int)$row['speed_limit_kmh'],
            aadt: (int)$row['aadt'],
            geoLocation: new GeoLocation(
                wkt: (string)($row['geo_location'] ?? ''),
                city: $row['city'] ?? null,
                street: $row['street'] ?? null
            )
        );
    }

    /**
     * @return array<int, Intersection>
     */
    private function fetchIntersections(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM intersections');
        if ($stmt === false) {
            return [];
        }

        $intersections = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $intersection = $this->createIntersectionFromRow($row);
            if ($intersection !== null) {
                $intersections[] = $intersection;
            }
        }

        return $intersections;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createIntersectionFromRow(array $row): ?Intersection
    {
        if (!isset($row['id'], $row['control_type'], $row['number_of_legs'], $row['has_cameras'], $row['aadt'], $row['spf_model_reference'])) {
            return null;
        }

        try {
            $controlType = IntersectionControlType::from($row['control_type']);
        } catch (\Throwable) {
            return null;
        }

        return new Intersection(
            id: (int)$row['id'],
            code: $row['code'] ?? null,
            controlType: $controlType,
            numberOfLegs: (int)$row['number_of_legs'],
            hasCameras: (bool)$row['has_cameras'],
            aadt: (int)$row['aadt'],
            spfModelReference: (string)$row['spf_model_reference'],
            geoLocation: new GeoLocation(
                wkt: (string)($row['geo_location'] ?? ''),
                city: $row['city'] ?? null,
                street: $row['street'] ?? null
            )
        );
    }

    private function generateId(): int
    {
        $this->nextId++;
        return $this->nextId;
    }
}


