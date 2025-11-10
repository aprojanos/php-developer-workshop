<?php
namespace SharedKernel\Model;

use SharedKernel\Domain\Aggregate\AggregateRoot;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;
use SharedKernel\DTO\AccidentLocationDTO;

abstract class AccidentBase extends AggregateRoot
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
        public readonly ?WeatherCondition $weatherCondition = null,
        public readonly ?RoadCondition $roadCondition = null,
        public readonly ?VisibilityCondition $visibilityCondition = null,
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
        
        if ($this->weatherCondition !== null) {
            $report .= "\nWeather: " . $this->weatherCondition->value;
        }
        
        if ($this->roadCondition !== null) {
            $report .= "\nRoad Conditions: " . $this->roadCondition->value;
        }
        
        if ($this->visibilityCondition !== null) {
            $report .= "\nVisibility: " . $this->visibilityCondition->value;
        }

        return $report;
    }
}
