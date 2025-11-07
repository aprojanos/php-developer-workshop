<?php

namespace App\Seeder;

use App\Enum\IntersectionControlType;

final class IntersectionSeeder
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function run(int $count = 10, bool $purgeExisting = true): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('IntersectionSeeder requires at least one intersection.');
        }

        if ($purgeExisting) {
            $this->purge();
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

        $stmt = $this->pdo->prepare(
            'INSERT INTO intersections (
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

            $payload = [
                'code' => sprintf('INT-%03d', $i + 1),
                'control_type' => $controlType->value,
                'number_of_legs' => $this->randomNumberOfLegs($controlType),
                'has_cameras' => $this->randomHasCameras($controlType),
                'aadt' => random_int(3500, 45000),
                'spf_model_reference' => $this->generateSpfModelReference($controlType, $i),
                'geo_location' => $this->randomPointWkt(),
                'city' => $city,
                'street' => $street,
            ];

            $stmt->execute([
                'code' => $payload['code'],
                'control_type' => $payload['control_type'],
                'number_of_legs' => $payload['number_of_legs'],
                'has_cameras' => $payload['has_cameras'] ? 'true' : 'false',
                'aadt' => $payload['aadt'],
                'spf_model_reference' => $payload['spf_model_reference'],
                'geo_location' => $payload['geo_location'],
                'city' => $payload['city'],
                'street' => $payload['street'],
            ]);
        }
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
            default => random_int(0, 4) === 0, // occasional cameras for other types
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


