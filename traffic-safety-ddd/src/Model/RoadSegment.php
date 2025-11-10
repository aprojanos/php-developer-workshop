<?php

namespace App\Model;

use SharedKernel\Enum\FunctionalClass;
use App\ValueObject\GeoLocation;

final readonly class RoadSegment
{
    public function __construct(
        public int $id,
        public ?string $code,
        public float $lengthKm,
        public int $laneCount,
        public FunctionalClass $functionalClass,
        public int $speedLimitKmh,
        public int $aadt,
        public GeoLocation $geoLocation
    ) {}
}

