<?php

namespace App\Seeder;

use App\Contract\RoadSegmentRepositoryInterface;
use App\Enum\FunctionalClass;
use App\Model\RoadSegment;
use App\ValueObject\GeoLocation;

final class RoadSegmentSeeder
{
    private int $nextId;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly RoadSegmentRepositoryInterface $repository,
    ) {
        $this->nextId = $this->fetchMaxId();
    }

    public function run(int $count = 10, bool $purgeExisting = true): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('RoadSegmentSeeder requires at least one road segment.');
        }

        if ($purgeExisting) {
            $this->purge();
            $this->nextId = 0;
        }

        $this->seed($count);
    }

    public function purge(): void
    {
        $this->pdo->exec('DELETE FROM road_segments');
    }

    private function seed(int $count): void
    {
        $functionalClasses = FunctionalClass::cases();
        $classCount = count($functionalClasses);
        $cities = ['Budapest', 'Debrecen', 'Szeged', 'Pécs', 'Győr'];
        $roadNames = ['Kossuth', 'Petőfi', 'Széchenyi', 'Rákóczi', 'Budaörsi', 'Váci', 'Andrássy'];
        $suffixes = ['út', 'utca', 'körút', 'fasor', 'sor'];

        for ($i = 0; $i < $count; $i++) {
            $functionalClass = $functionalClasses[$i % $classCount];
            $roadSegment = $this->createRoadSegment(
                index: $i,
                functionalClass: $functionalClass,
                city: $cities[$i % count($cities)],
                streetName: sprintf('%s %s', $roadNames[$i % count($roadNames)], $suffixes[$i % count($suffixes)])
            );

            $this->repository->save($roadSegment);
        }
    }

    private function createRoadSegment(
        int $index,
        FunctionalClass $functionalClass,
        string $city,
        string $streetName
    ): RoadSegment {
        return new RoadSegment(
            id: $this->generateId(),
            code: sprintf('SEG-%03d', $index + 1),
            lengthKm: $this->randomLengthKm($functionalClass),
            laneCount: $this->randomLaneCount($functionalClass),
            functionalClass: $functionalClass,
            speedLimitKmh: $this->randomSpeedLimit($functionalClass),
            aadt: $this->randomAadt($functionalClass),
            geoLocation: new GeoLocation(
                wkt: $this->randomPointWkt(),
                city: $city,
                street: $streetName,
            ),
        );
    }

    private function fetchMaxId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM road_segments');
        $result = $stmt !== false ? $stmt->fetchColumn() : 0;

        return (int)$result;
    }

    private function generateId(): int
    {
        $this->nextId++;

        return $this->nextId;
    }

    private function randomLengthKm(FunctionalClass $functionalClass): float
    {
        return match ($functionalClass) {
            FunctionalClass::HIGHWAY => random_int(500, 2000) / 100.0,
            FunctionalClass::RURAL => random_int(300, 1500) / 100.0,
            FunctionalClass::URBAN => random_int(50, 500) / 100.0,
        };
    }

    private function randomLaneCount(FunctionalClass $functionalClass): int
    {
        $highwayOptions = [4, 6, 8];
        $ruralOptions = [2, 4];
        return match ($functionalClass) {
            FunctionalClass::HIGHWAY => $highwayOptions[array_rand($highwayOptions)],
            FunctionalClass::RURAL => $ruralOptions[array_rand($ruralOptions)],
            FunctionalClass::URBAN => random_int(2, 6),
        };
    }

    private function randomSpeedLimit(FunctionalClass $functionalClass): int
    {
        return match ($functionalClass) {
            FunctionalClass::HIGHWAY => random_int(100, 130),
            FunctionalClass::RURAL => random_int(70, 100),
            FunctionalClass::URBAN => random_int(30, 60),
        };
    }

    private function randomAadt(FunctionalClass $functionalClass): int
    {
        return match ($functionalClass) {
            FunctionalClass::HIGHWAY => random_int(30000, 85000),
            FunctionalClass::RURAL => random_int(8000, 30000),
            FunctionalClass::URBAN => random_int(5000, 35000),
        };
    }

    private function randomPointWkt(): string
    {
        $baseLat = 47.4900;
        $baseLon = 19.0400;
        $lat1 = $baseLat + (random_int(-4000, 4000) / 100000.0);
        $lon1 = $baseLon + (random_int(-4000, 4000) / 100000.0);
        $lat2 = $lat1 + (random_int(500, 1500) / 100000.0);
        $lon2 = $lon1 + (random_int(500, 1500) / 100000.0);
        return sprintf('LINESTRING(%.5f %.5f, %.5f %.5f)', $lon1, $lat1, $lon2, $lat2);
    }
}


