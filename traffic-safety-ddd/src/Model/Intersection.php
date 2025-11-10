<?php

namespace App\Model;

use SharedKernel\Enum\IntersectionControlType;
use App\ValueObject\GeoLocation;

final readonly class Intersection
{
    public function __construct(
        public int $id,
        public ?string $code,
        public IntersectionControlType $controlType,
        public int $numberOfLegs,
        public bool $hasCameras,
        public int $aadt,
        public string $spfModelReference,
        public GeoLocation $geoLocation
    ) {}
}

