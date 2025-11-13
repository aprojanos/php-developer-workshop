<?php

namespace RoadNetworkContext\Infrastructure\Seeder;

use SharedKernel\Contract\IntersectionRepositoryInterface;
use SharedKernel\Enum\IntersectionControlType;
use SharedKernel\Model\Intersection;
use SharedKernel\ValueObject\GeoLocation;

final class IntersectionSeeder
{
    private int $nextId;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly IntersectionRepositoryInterface $repository,
    ) {
        $this->nextId = $this->fetchMaxId();
    }

    public function run(int $count = 10, bool $purgeExisting = true): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('IntersectionSeeder requires at least one intersection.');
        }

        if ($purgeExisting) {
            $this->purge();
            $this->nextId = 0;
        }

        $this->seed($count);
    }

    public function purge(): void
    {
        $this->pdo->exec('DELETE FROM intersections');
    }

    private function seed(int $count): void
    {
        $controlTypes = IntersectionControlType::cases();
        $controlTypeCount = count($controlTypes);
        $cities = ['Budapest', 'Debrecen', 'Szeged', 'Pécs', 'Győr'];
        $streetPrefixes = ['Kossuth', 'Petőfi', 'Széchenyi', 'Rákóczi', 'Bajcsy'];
        $streetSuffixes = ['út', 'utca', 'körút', 'tér', 'sétány'];

        for ($i = 0; $i < $count; $i++) {
            $controlType = $controlTypes[$i % $controlTypeCount];
            $city = $cities[$i % count($cities)];
            $street = sprintf(
                '%s %s & %s %s',
                $streetPrefixes[$i % count($streetPrefixes)],
                $streetSuffixes[$i % count($streetSuffixes)],
                $streetPrefixes[($i + 1) % count($streetPrefixes)],
                $streetSuffixes[($i + 2) % count($streetSuffixes)]
            );

            $intersection = $this->createIntersection(
                index: $i,
                controlType: $controlType,
                city: $city,
                street: $street
            );

            $this->repository->save($intersection);
        }
    }

    private function createIntersection(
        int $index,
        IntersectionControlType $controlType,
        string $city,
        string $street
    ): Intersection {
        return new Intersection(
            id: $this->generateId(),
            code: sprintf('INT-%03d', $index + 1),
            controlType: $controlType,
            numberOfLegs: $this->randomNumberOfLegs($controlType),
            hasCameras: $this->randomHasCameras($controlType),
            aadt: random_int(3500, 45000),
            spfModelReference: $this->generateSpfModelReference($controlType, $index),
            geoLocation: new GeoLocation(
                wkt: $this->randomPointWkt(),
                city: $city,
                street: $street,
            ),
        );
    }

    private function fetchMaxId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM intersections');
        $result = $stmt !== false ? $stmt->fetchColumn() : 0;

        return (int)$result;
    }

    private function generateId(): int
    {
        $this->nextId++;

        return $this->nextId;
    }

    private function randomNumberOfLegs(IntersectionControlType $controlType): int
    {
        return match ($controlType) {
            IntersectionControlType::ROUNDABOUT => random_int(3, 5),
            IntersectionControlType::TRAFFIC_LIGHT => random_int(4, 6),
            default => random_int(3, 4),
        };
    }

    private function randomHasCameras(IntersectionControlType $controlType): bool
    {
        return match ($controlType) {
            IntersectionControlType::TRAFFIC_LIGHT, IntersectionControlType::SIGNALLED => random_int(0, 1) === 1,
            default => random_int(0, 4) === 0,
        };
    }

    private function generateSpfModelReference(IntersectionControlType $controlType, int $index): string
    {
        $base = match ($controlType) {
            IntersectionControlType::TRAFFIC_LIGHT => 'HSM Urban Signalized',
            IntersectionControlType::SIGNALLED => 'HSM Half-Signalized',
            IntersectionControlType::PRIORITY => 'HSM Stop/Yield Controlled',
            IntersectionControlType::ROUNDABOUT => 'HSM Single-Lane Roundabout',
        };

        return sprintf('%s #%d', $base, $index + 1);
    }

    private function randomPointWkt(): string
    {
        $baseLat = 47.5000;
        $baseLon = 19.0500;
        $lat = $baseLat + (random_int(-3000, 3000) / 100000.0);
        $lon = $baseLon + (random_int(-3000, 3000) / 100000.0);

        return sprintf('POINT(%.5f %.5f)', $lon, $lat);
    }
}

