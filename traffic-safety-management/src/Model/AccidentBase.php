<?php
namespace App\Model;

use App\Enum\InjurySeverity;
use App\Enum\AccidentType;
use App\Enum\CollisionType;
use App\Enum\CauseFactor;
use App\Enum\WeatherConditions;
use App\Enum\RoadConditions;
use App\Enum\VisibilityConditions;
use App\DTO\AccidentLocationDTO;

abstract class AccidentBase
{
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly AccidentLocationDTO $location,
        public readonly float $cost,
        public readonly ?InjurySeverity $severity,
        public readonly ?string $locationDescription = null,
        public readonly ?CollisionType $collisionType = null,
        public readonly ?CauseFactor $causeFactor = null,
        public readonly ?WeatherConditions $weatherConditions = null,
        public readonly ?RoadConditions $roadConditions = null,
        public readonly ?VisibilityConditions $visibilityConditions = null,
        public readonly int $injuredPersonsCount = 0
    ) {}

    abstract public function getType(): AccidentType;
    abstract public function getSeverityLabel(): string;
    abstract public function requiresImmediateAttention(): bool;
        
    public function getDaysSinceOccurrence(): int
    {
        $now = new \DateTimeImmutable();
        return $now->diff($this->occurredAt)->days;
    }
        
    public function getLocationDescription(): string
    {
        if ($this->locationDescription !== null) {
            return $this->locationDescription;
        }
        
        $typeLabel = $this->location->locationType->value;
        return sprintf(
            '%s #%d (%.6f, %.6f)',
            ucfirst($typeLabel),
            $this->location->locationId,
            $this->location->latitude,
            $this->location->longitude
        );
    }

    public function formatReport(): string
    {
        $report = sprintf(
            "Accident #%d\n" .
            "Type: %s\n" .
            "Location: %s\n" .
            "Occurred: %s\n" .
            "Days Since: %d\n" .
            "Cost: $%.2f\n" .
            "Injured Persons: %d",
            $this->id,
            $this->getSeverityLabel(),
            $this->getLocationDescription(),
            $this->occurredAt->format('Y-m-d H:i:s'),
            $this->getDaysSinceOccurrence(),
            $this->cost,
            $this->injuredPersonsCount
        );

        if ($this->collisionType !== null) {
            $report .= "\nCollision Type: " . $this->collisionType->value;
        }
        
        if ($this->causeFactor !== null) {
            $report .= "\nCause Factor: " . $this->causeFactor->value;
        }
        
        if ($this->weatherConditions !== null) {
            $report .= "\nWeather: " . $this->weatherConditions->value;
        }
        
        if ($this->roadConditions !== null) {
            $report .= "\nRoad Conditions: " . $this->roadConditions->value;
        }
        
        if ($this->visibilityConditions !== null) {
            $report .= "\nVisibility: " . $this->visibilityConditions->value;
        }

        return $report;
    }
}
