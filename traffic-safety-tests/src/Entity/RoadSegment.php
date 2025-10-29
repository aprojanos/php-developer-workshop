<?php

namespace TrafficSafetyTests\Entity;

class RoadSegment
{
    private int $id;
    private string $name;
    private float $length; // km-ben
    private int $speedLimit; // km/h-ban
    private int $trafficVolume; // napi forgalom
    private string $roadType; // 'highway', 'urban', 'rural'

    public function __construct(
        string $name,
        float $length,
        int $speedLimit,
        int $trafficVolume,
        string $roadType
    ) {
        $this->name = $name;
        $this->length = $length;
        $this->speedLimit = $speedLimit;
        $this->trafficVolume = $trafficVolume;
        $this->roadType = $roadType;
    }

    // Getter metódusok
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getLength(): float { return $this->length; }
    public function getSpeedLimit(): int { return $this->speedLimit; }
    public function getTrafficVolume(): int { return $this->trafficVolume; }
    public function getRoadType(): string { return $this->roadType; }

    // Setter metódusok
    public function setId(int $id): void { $this->id = $id; }
    public function setSpeedLimit(int $speedLimit): void { 
        $this->speedLimit = $speedLimit; 
    }
    public function setTrafficVolume(int $trafficVolume): void { 
        $this->trafficVolume = $trafficVolume; 
    }

    public function calculateRiskFactor(): float
    {
        $baseRisk = 1.0;
        
        // Sebességkorlát alapú kockázat
        if ($this->speedLimit > 90) {
            $baseRisk *= 1.5;
        } elseif ($this->speedLimit > 50) {
            $baseRisk *= 1.2;
        }

        // Forgalom alapú kockázat
        if ($this->trafficVolume > 50000) {
            $baseRisk *= 2.0;
        } elseif ($this->trafficVolume > 20000) {
            $baseRisk *= 1.5;
        }

        // Út típusa alapú kockázat
        switch ($this->roadType) {
            case 'highway':
                $baseRisk *= 1.8;
                break;
            case 'urban':
                $baseRisk *= 1.3;
                break;
            case 'rural':
                $baseRisk *= 1.1;
                break;
        }

        return round($baseRisk, 2);
    }
}
