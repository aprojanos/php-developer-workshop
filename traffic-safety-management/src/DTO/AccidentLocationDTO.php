<?php

namespace App\DTO;

use App\Enum\LocationType;

final readonly class AccidentLocationDTO
{
    public function __construct(
        public LocationType $locationType,
        public int $locationId,
        public float $latitude,
        public float $longitude,
        public ?float $distanceFromStart = null
    ) {
        // Validate that distanceFromStart is provided for roadsegments
        if ($this->locationType === LocationType::ROADSEGMENT && $this->distanceFromStart === null) {
            throw new \InvalidArgumentException('Distance from start point is required for roadsegment locations');
        }
    }

    public function getRoadSegmentId(): ?int
    {
        return $this->locationType === LocationType::ROADSEGMENT ? $this->locationId : null;
    }

    public function getIntersectionId(): ?int
    {
        return $this->locationType === LocationType::INTERSECTION ? $this->locationId : null;
    }

    public function getDistanceFromStart(): ?float
    {
        return $this->distanceFromStart;
    }
}

