<?php

namespace TrafficSafetyTests\Entity;

class Accident
{
    private int $id;
    private \DateTime $occurredAt;
    private string $location;
    private string $severity; // 'minor', 'serious', 'fatal'
    private array $involvedVehicles;
    private ?string $weatherConditions;
    private ?int $intersectionId;
    private ?int $roadSegmentId;

    public function __construct(
        string $location,
        string $severity,
        array $involvedVehicles,
        ?string $weatherConditions = null,
        ?int $intersectionId = null,
        ?int $roadSegmentId = null
    ) {
        $this->occurredAt = new \DateTime();
        $this->location = $location;
        $this->severity = $severity;
        $this->involvedVehicles = $involvedVehicles;
        $this->weatherConditions = $weatherConditions;
        $this->intersectionId = $intersectionId;
        $this->roadSegmentId = $roadSegmentId;
    }

    // Getter metódusok
    public function getId(): int { return $this->id; }
    public function getOccurredAt(): \DateTime { return $this->occurredAt; }
    public function getLocation(): string { return $this->location; }
    public function getSeverity(): string { return $this->severity; }
    public function getInvolvedVehicles(): array { return $this->involvedVehicles; }
    public function getWeatherConditions(): ?string { return $this->weatherConditions; }
    public function getIntersectionId(): ?int { return $this->intersectionId; }
    public function getRoadSegmentId(): ?int { return $this->roadSegmentId; }

    // Setter metódusok
    public function setId(int $id): void { $this->id = $id; }
    public function setOccurredAt(\DateTime $occurredAt): void { 
        $this->occurredAt = $occurredAt; 
    }

    public function getSeverityWeight(): int
    {
        switch ($this->severity) {
            case 'fatal':
                return 10;
            case 'serious':
                return 5;
            case 'minor':
                return 2;
            default:
                return 1;
        }
    }

    public function isWeatherRelated(): bool
    {
        $adverseWeather = ['rain', 'snow', 'fog', 'ice'];
        return in_array($this->weatherConditions, $adverseWeather);
    }

    public function getInvolvedVehicleCount(): int
    {
        return count($this->involvedVehicles);
    }
}
