<?php

namespace TrafficSafetyTests\Entity;

class Intersection
{
    private int $id;
    private string $name;
    private array $connectedSegments = [];
    private string $intersectionType; // 'cross', 't-junction', 'roundabout'
    private bool $hasTrafficLights;
    private int $accidentCount;

    public function __construct(
        string $name,
        string $intersectionType,
        bool $hasTrafficLights = false
    ) {
        $this->name = $name;
        $this->intersectionType = $intersectionType;
        $this->hasTrafficLights = $hasTrafficLights;
        $this->accidentCount = 0;
    }

    // Getter metódusok
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getConnectedSegments(): array { return $this->connectedSegments; }
    public function getIntersectionType(): string { return $this->intersectionType; }
    public function hasTrafficLights(): bool { return $this->hasTrafficLights; }
    public function getAccidentCount(): int { return $this->accidentCount; }

    // Setter metódusok
    public function setId(int $id): void { $this->id = $id; }
    public function setHasTrafficLights(bool $hasTrafficLights): void {
        $this->hasTrafficLights = $hasTrafficLights;
    }

    public function addConnectedSegment(RoadSegment $segment): void
    {
        if (!in_array($segment, $this->connectedSegments, true)) {
            $this->connectedSegments[] = $segment;
        }
    }

    public function incrementAccidentCount(): void
    {
        $this->accidentCount++;
    }

    public function calculateSafetyScore(): int
    {
        $score = 100;

        // Balesetek alapján
        $score -= $this->accidentCount * 5;

        // Közlekedési lámpa hatása
        if ($this->hasTrafficLights) {
            $score += 20;
        }

        // Kereszteződés típusa alapján
        switch ($this->intersectionType) {
            case 'roundabout':
                $score += 15;
                break;
            case 'cross':
                $score -= 10;
                break;
            case 't-junction':
                $score -= 5;
                break;
        }

        // Csatlakozó útszakaszok száma
        $segmentCount = count($this->connectedSegments);
        if ($segmentCount > 4) {
            $score -= 15;
        } elseif ($segmentCount > 2) {
            $score -= 5;
        }

        return max(0, $score);
    }
}
