<?php
namespace App\Model;

use App\Enum\InjurySeverity;
use App\Enum\AccidentType;

abstract class AccidentBase
{
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly string $location,
        public readonly float $cost,
        public readonly ?InjurySeverity $severity,
        public readonly ?int $roadSegmentId = null,
        public readonly ?int $intersectionId = null
    ) {}

    abstract public function getType(): AccidentType;
    abstract public function getSeverityLabel(): string;
    abstract public function requiresImmediateAttention(): bool;
    
    public function getDaysSinceOccurrence(): int
    {
        $now = new \DateTimeImmutable();
        return $now->diff($this->occurredAt)->days;
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
            $this->location,
            $this->occurredAt->format('Y-m-d H:i:s'),
            $this->getDaysSinceOccurrence(),
            $this->cost,
        );
    }
}
