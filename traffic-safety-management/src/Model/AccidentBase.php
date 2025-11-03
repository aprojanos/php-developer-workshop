<?php
namespace App\Model;

use App\Enum\InjurySeverity;
use App\Enum\AccidentType;
use App\DTO\AccidentLocationDTO;

abstract class AccidentBase
{
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly AccidentLocationDTO $location,
        public readonly float $cost,
        public readonly ?InjurySeverity $severity,
        public readonly ?string $locationDescription = null
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
        return sprintf(
            "Accident #%d\n" .
            "Type: %s\n" .
            "Location: %s\n" .
            "Occurred: %s\n" .
            "Days Since: %d\n" .
            "Cost: $%.2f\n" .
            "Priority: %d\n" .
            "Risk Level: %s",
            $this->id,
            $this->getSeverityLabel(),
            $this->getLocationDescription(),
            $this->occurredAt->format('Y-m-d H:i:s'),
            $this->getDaysSinceOccurrence(),
            $this->cost,
        );
    }
}
