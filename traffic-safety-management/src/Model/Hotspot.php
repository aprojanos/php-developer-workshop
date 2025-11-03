<?php

namespace App\Model;

use App\Enum\HotspotStatus;
use App\Enum\AccidentType;
use App\ValueObject\TimePeriod;

final readonly class Hotspot
{
    /**
     * @param array<AccidentType, int> $observedCrashes
     * @param array<string, mixed>|null $screeningParameters
     */
    public function __construct(
        public int $id,
        public RoadSegment|Intersection $location,
        public TimePeriod $period,
        public array $observedCrashes,
        public float $expectedCrashes,
        public float $riskScore,
        public HotspotStatus $status,
        public ?array $screeningParameters = null
    ) {
        // Validate observed crashes structure
        foreach ($this->observedCrashes as $type => $count) {
            if (!$type instanceof AccidentType) {
                throw new \InvalidArgumentException('observedCrashes keys must be AccidentType instances');
            }
            if (!is_int($count) || $count < 0) {
                throw new \InvalidArgumentException('observedCrashes values must be non-negative integers');
            }
        }
    }
}

