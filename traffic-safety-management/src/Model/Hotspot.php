<?php

namespace App\Model;

use App\Enum\HotspotStatus;
use App\ValueObject\TimePeriod;
use App\ValueObject\ObservedCrashes;

final readonly class Hotspot
{
    /**
     * @param array<string, mixed>|null $screeningParameters
     */
    public function __construct(
        public int $id,
        public RoadSegment|Intersection $location,
        public TimePeriod $period,
        public ObservedCrashes $observedCrashes,
        public float $expectedCrashes,
        public float $riskScore,
        public HotspotStatus $status,
        public ?array $screeningParameters = null
    ) {}
}

