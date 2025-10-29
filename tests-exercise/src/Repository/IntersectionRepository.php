<?php

namespace TrafficSafetyTests\Repository;

use TrafficSafetyTests\Entity\Intersection;
use TrafficSafetyTests\Entity\RoadSegment;
use PDO;

class IntersectionRepository
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function save(Intersection $intersection): bool
    {
        $sql = "INSERT INTO intersections (name, intersection_type, has_traffic_lights, accident_count) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($sql);
        
        return $stmt->execute([
            $intersection->getName(),
            $intersection->getIntersectionType(),
            $intersection->hasTrafficLights() ? 1 : 0,
            $intersection->getAccidentCount()
        ]);
    }

    public function findById(int $id): ?Intersection
    {
        $sql = "SELECT * FROM intersections WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$id]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }

        return $this->hydrateIntersection($data);
    }

    public function findMostDangerous(int $limit = 5): array
    {
        $sql = "SELECT * FROM intersections ORDER BY accident_count DESC LIMIT ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$limit]);
        
        $intersections = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $intersections[] = $this->hydrateIntersection($data);
        }
        
        return $intersections;
    }

    public function updateAccidentCount(int $intersectionId, int $count): bool
    {
        $sql = "UPDATE intersections SET accident_count = ? WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        
        return $stmt->execute([$count, $intersectionId]);
    }

    private function hydrateIntersection(array $data): Intersection
    {
        $intersection = new Intersection(
            $data['name'],
            $data['intersection_type'],
            (bool)$data['has_traffic_lights']
        );
        
        $intersection->setId($data['id']);
        
        // Jelenlegi implementációban nem töltjük be a connected segments-eket
        // Ez egy külön lekérdezés lenne a real world-ben
        
        return $intersection;
    }
}
