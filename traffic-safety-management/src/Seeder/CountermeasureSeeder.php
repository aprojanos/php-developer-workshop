<?php

namespace App\Seeder;

use App\Enum\CollisionType;
use App\Enum\FunctionalClass;
use App\Enum\InjurySeverity;
use App\Enum\IntersectionControlType;
use App\Enum\IntersectionType;
use App\Enum\LifecycleStatus;
use App\Enum\RoadClassification;
use App\Enum\TargetType;
use App\Factory\CountermeasureFactory;
use App\Seeder\Exception\MissingCountermeasureTargetReferenceException;
use App\Service\CountermeasureService;
use Random\Randomizer;

final class CountermeasureSeeder
{
    private int $nextId;

    /** @var array<int, array{id:int, code:string, control_type:IntersectionControlType}> */
    private array $intersections = [];

    /** @var array<int, array{id:int, code:string, functional_class:FunctionalClass}> */
    private array $roadSegments = [];

    /** @var array<int, IntersectionControlType> */
    private array $intersectionControls = [];

    private readonly Randomizer $randomizer;

    public function __construct(
        private readonly CountermeasureService $countermeasureService,
        private readonly \PDO $pdo,
    ) {
        $this->nextId = $this->fetchMaxId();
        $this->intersections = $this->fetchIntersections();
        $this->roadSegments = $this->fetchRoadSegments();
        $this->randomizer = new Randomizer();
    }

    public function run(int $intersectionCount = 5, int $roadSegmentCount = 5, bool $purgeExisting = true): void
    {
        if ($purgeExisting) {
            $this->purge();
            $this->nextId = 0;
        }

        if ($intersectionCount > 0 && empty($this->intersections)) {
            throw new MissingCountermeasureTargetReferenceException('Cannot seed intersection countermeasures without intersections.');
        }

        if ($roadSegmentCount > 0 && empty($this->roadSegments)) {
            throw new MissingCountermeasureTargetReferenceException('Cannot seed road-segment countermeasures without road segments.');
        }

        $this->seedIntersectionCountermeasures($intersectionCount);
        $this->seedRoadSegmentCountermeasures($roadSegmentCount);
    }

    private function purge(): void
    {
        $this->pdo->exec('DELETE FROM countermeasures');
    }

    private function fetchMaxId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM countermeasures');
        $result = $stmt !== false ? $stmt->fetchColumn() : 0;
        return (int)$result;
    }

    private function seedIntersectionCountermeasures(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $payload = $this->generateIntersectionPayload($i);
            $countermeasure = CountermeasureFactory::create($payload);
            $this->countermeasureService->create($countermeasure);
        }
    }

    private function seedRoadSegmentCountermeasures(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $payload = $this->generateRoadSegmentPayload($i);
            $countermeasure = CountermeasureFactory::create($payload);
            $this->countermeasureService->create($countermeasure);
        }
    }

    private function generateIntersectionPayload(int $index): array
    {
        $intersection = $this->intersections[array_rand($this->intersections)];
        if (empty($this->intersectionControls)) {
            $controls = [];
            foreach ($this->intersections as $item) {
                $controls[$item['control_type']->value] = $item['control_type'];
            }

            if (empty($controls)) {
                foreach (IntersectionControlType::cases() as $case) {
                    $controls[$case->value] = $case;
                }
            }

            $this->intersectionControls = array_values($controls);
        }

        $controlTypes = $this->pickSubset($this->intersectionControls, 1, 2);

        $name = sprintf('Intersection CM %02d - %s enhancements', $index + 1, $intersection['code']);

        return [
            'id' => $this->generateId(),
            'name' => $name,
            'target_type' => TargetType::INTERSECTION->value,
            'applicability_rules' => [
                'intersection_types' => $this->pickIntersectionTypes(),
                'intersection_control_types' => array_map(fn(IntersectionControlType $type) => $type->value, $controlTypes),
            ],
            'affected_collision_types' => $this->pickCollisionTypes(),
            'affected_severities' => $this->pickSeverities(),
            'cmf' => $this->randomizer->getFloat(0.6, 0.95),
            'lifecycle_status' => $this->randomLifecycleStatus()->value,
            'implementation_cost' => [
                'amount' => $this->randomMoneyAmount(75000, 250000),
                'currency' => 'USD',
            ],
            'expected_annual_savings' => $this->randomMoneyAmount(20000, 120000),
            'evidence' => sprintf('Performance study for %s control improvements.', $intersection['code']),
        ];
    }

    private function generateRoadSegmentPayload(int $index): array
    {
        $segment = $this->roadSegments[array_rand($this->roadSegments)];
        $classifications = $this->pickRoadClassifications($segment['functional_class']);

        $name = sprintf('Road CM %02d - %s safety program', $index + 1, $segment['code']);

        return [
            'id' => $this->generateId(),
            'name' => $name,
            'target_type' => TargetType::ROAD_SEGMENT->value,
            'applicability_rules' => [
                'road_classifications' => array_map(fn(RoadClassification $class) => $class->value, $classifications),
            ],
            'affected_collision_types' => $this->pickCollisionTypes(),
            'affected_severities' => $this->pickSeverities(),
            'cmf' => $this->randomizer->getFloat(0.5, 0.9),
            'lifecycle_status' => $this->randomLifecycleStatus()->value,
            'implementation_cost' => [
                'amount' => $this->randomMoneyAmount(50000, 400000),
                'currency' => 'USD',
            ],
            'expected_annual_savings' => $this->randomMoneyAmount(15000, 180000),
            'evidence' => sprintf('Before-after analysis on %s.', $segment['code']),
        ];
    }

    /**
     * @return array<int, array{id:int, code:string, control_type:IntersectionControlType}>
     */
    private function fetchIntersections(): array
    {
        $stmt = $this->pdo->query("SELECT id, COALESCE(code, 'INT-' || LPAD(id::text, 3, '0')) AS code, control_type FROM intersections");
        if ($stmt === false) {
            return [];
        }

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($row['id'], $row['code'], $row['control_type'])) {
                continue;
            }

            try {
                $controlType = IntersectionControlType::from($row['control_type']);
            } catch (\Throwable) {
                continue;
            }

            $result[] = [
                'id' => (int)$row['id'],
                'code' => (string)$row['code'],
                'control_type' => $controlType,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{id:int, code:string, functional_class:FunctionalClass}>
     */
    private function fetchRoadSegments(): array
    {
        $stmt = $this->pdo->query("SELECT id, COALESCE(code, 'SEG-' || LPAD(id::text, 3, '0')) AS code, functional_class FROM road_segments");
        if ($stmt === false) {
            return [];
        }

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($row['id'], $row['code'], $row['functional_class'])) {
                continue;
            }

            try {
                $functionalClass = FunctionalClass::from($row['functional_class']);
            } catch (\Throwable) {
                continue;
            }

            $result[] = [
                'id' => (int)$row['id'],
                'code' => (string)$row['code'],
                'functional_class' => $functionalClass,
            ];
        }

        return $result;
    }

    /**
     * @return array<IntersectionType>
     */
    private function pickIntersectionTypes(): array
    {
        $count = random_int(1, 2);
        $types = IntersectionType::cases();
        shuffle($types);
        return array_slice($types, 0, $count);
    }

    /**
     * @return array<CollisionType::value>
     */
    private function pickCollisionTypes(): array
    {
        $cases = CollisionType::cases();
        shuffle($cases);
        $selected = array_slice($cases, 0, random_int(1, min(3, count($cases))));
        return array_map(fn(CollisionType $type) => $type->value, $selected);
    }

    /**
     * @return array<InjurySeverity::value>
     */
    private function pickSeverities(): array
    {
        $cases = InjurySeverity::cases();
        shuffle($cases);
        $selected = array_slice($cases, 0, random_int(1, min(3, count($cases))));
        return array_map(fn(InjurySeverity $severity) => $severity->value, $selected);
    }

    /**
     * @return array<RoadClassification>
     */
    private function pickRoadClassifications(FunctionalClass $functionalClass): array
    {
        $mapping = $this->roadClassificationByFunctional($functionalClass);
        shuffle($mapping);
        $count = random_int(1, count($mapping));
        return array_slice($mapping, 0, $count);
    }

    /**
     * @return array<RoadClassification>
     */
    private function roadClassificationByFunctional(FunctionalClass $functionalClass): array
    {
        return match ($functionalClass) {
            FunctionalClass::HIGHWAY => [RoadClassification::ONE, RoadClassification::TWO],
            FunctionalClass::RURAL => [RoadClassification::THREE, RoadClassification::FOUR],
            FunctionalClass::URBAN => [RoadClassification::FIVE, RoadClassification::SIX],
        };
    }

    private function randomLifecycleStatus(): LifecycleStatus
    {
        $cases = LifecycleStatus::cases();
        return $cases[array_rand($cases)];
    }

    private function randomMoneyAmount(int $min, int $max): float
    {
        return round((float)random_int($min, $max), 2);
    }

    private function generateId(): int
    {
        $this->nextId++;
        return $this->nextId;
    }

    /**
     * @template T
     * @param array<T> $items
     * @return array<T>
     */
    private function pickSubset(array $items, int $min, int $max): array
    {
        $max = min($max, count($items));
        $min = min($min, $max);
        $count = max($min, random_int($min, max($min, $max)));
        $shuffled = $items;
        shuffle($shuffled);
        return array_slice($shuffled, 0, $count);
    }
}


