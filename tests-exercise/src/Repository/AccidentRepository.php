<?php

namespace TrafficSafetyTests\Repository;

use TrafficSafetyTests\Entity\Accident;
use PDO;

class AccidentRepository
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function save(Accident $accident): bool
    {
        $sql = "INSERT INTO accidents (
            occurred_at, location, severity, involved_vehicles, 
            weather_conditions, intersection_id, road_segment_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->database->prepare($sql);
        
        return $stmt->execute([
            $accident->getOccurredAt()->format('Y-m-d H:i:s'),
            $accident->getLocation(),
            $accident->getSeverity(),
            json_encode($accident->getInvolvedVehicles()),
            $accident->getWeatherConditions(),
            $accident->getIntersectionId(),
            $accident->getRoadSegmentId()
        ]);
    }

    public function findById(int $id): ?Accident
    {
        $sql = "SELECT * FROM accidents WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$id]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }

        return $this->hydrateAccident($data);
    }

    public function findByIntersection(int $intersectionId): array
    {
        $sql = "SELECT * FROM accidents WHERE intersection_id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$intersectionId]);
        
        $accidents = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $accidents[] = $this->hydrateAccident($data);
        }
        
        return $accidents;
    }

    public function findBySeverity(string $severity): array
    {
        $sql = "SELECT * FROM accidents WHERE severity = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$severity]);
        
        $accidents = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $accidents[] = $this->hydrateAccident($data);
        }
        
        return $accidents;
    }

    public function getAccidentsInPeriod(\DateTime $start, \DateTime $end): array
    {
        $sql = "SELECT * FROM accidents WHERE occurred_at BETWEEN ? AND ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ]);
        
        $accidents = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $accidents[] = $this->hydrateAccident($data);
        }
        
        return $accidents;
    }

    private function hydrateAccident(array $data): Accident
    {
        $accident = new Accident(
            $data['location'],
            $data['severity'],
            json_decode($data['involved_vehicles'], true),
            $data['weather_conditions'],
            $data['intersection_id'],
            $data['road_segment_id']
        );
        
        $accident->setId($data['id']);
        $accident->setOccurredAt(new \DateTime($data['occurred_at']));
        
        return $accident;
    }
}
