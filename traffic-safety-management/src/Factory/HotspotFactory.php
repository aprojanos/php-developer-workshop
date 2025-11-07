<?php
namespace App\Factory;

use App\Model\Hotspot;
use App\ValueObject\TimePeriod;
use App\ValueObject\ObservedCrashes;
use App\Enum\HotspotStatus;
use App\Enum\AccidentType;

final class HotspotFactory
{
    public static function create(array $data): Hotspot
    {
        return new Hotspot(
            id: (int)$data['id'],
            location: $data['location'],
            period: new TimePeriod(
                startDate: new \DateTimeImmutable($data['period_start']),
                endDate: new \DateTimeImmutable($data['period_end'])
            ),
            observedCrashes: self::createObservedCrashes($data['observed_crashes'] ?? []),
            expectedCrashes: (float)($data['expected_crashes'] ?? 0),
            riskScore: (float)($data['risk_score'] ?? 0),
            status: HotspotStatus::from($data['status'] ?? 'open'),
            screeningParameters: $data['screening_parameters'] ?? null
        );
    }

    private static function createObservedCrashes(array $crashesData): ObservedCrashes
    {
        $observedCrashes = [];
        foreach ($crashesData as $type => $count) {
            $enum = $type instanceof AccidentType ? $type : AccidentType::from((string)$type);
            $observedCrashes[$enum->value] = (int)$count;
        }
        return new ObservedCrashes($observedCrashes);
    }
}
