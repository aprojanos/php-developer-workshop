<?php

namespace SharedKernel\DTO;

use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\ValueObject\TimePeriod;

final readonly class AccidentSearchCriteria
{
    public function __construct(
        public ?TimePeriod $occurredAtInterval = null,
        public ?AccidentLocationDTO $location = null,
        public ?InjurySeverity $severity = null,
        public ?AccidentType $type = null,
        public ?CollisionType $collisionType = null,
        public ?CauseFactor $causeFactor = null,
        public ?WeatherCondition $weatherCondition = null,
        public ?RoadCondition $roadCondition = null,
        public ?VisibilityCondition $visibilityCondition = null,
        public ?int $injuredPersonsCount = null
    ) {}
}

