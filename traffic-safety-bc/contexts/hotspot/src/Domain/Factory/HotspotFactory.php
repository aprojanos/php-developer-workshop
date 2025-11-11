<?php

namespace HotspotContext\Domain\Factory;

use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\HotspotStatus;
use SharedKernel\Model\Hotspot;
use SharedKernel\ValueObject\ObservedCrashes;
use SharedKernel\ValueObject\TimePeriod;

final class HotspotFactory
{
    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @param array<string|AccidentType, int> $crashesData
     */
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

