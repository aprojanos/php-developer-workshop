<?php

namespace TrafficSafetyTests\Service;

use TrafficSafetyTests\Repository\AccidentRepository;
use TrafficSafetyTests\Repository\IntersectionRepository;

class SafetyAnalysisService
{
    private AccidentRepository $accidentRepository;
    private IntersectionRepository $intersectionRepository;

    public function __construct(
        AccidentRepository $accidentRepository,
        IntersectionRepository $intersectionRepository
    ) {
        $this->accidentRepository = $accidentRepository;
        $this->intersectionRepository = $intersectionRepository;
    }

    public function analyzeIntersectionSafety(int $intersectionId): array
    {
        $intersection = $this->intersectionRepository->findById($intersectionId);
        if (!$intersection) {
            throw new \InvalidArgumentException("Intersection not found with ID: {$intersectionId}");
        }

        $accidents = $this->accidentRepository->findByIntersection($intersectionId);
        
        $analysis = [
            'intersection_name' => $intersection->getName(),
            'safety_score' => $intersection->calculateSafetyScore(),
            'total_accidents' => count($accidents),
            'accidents_by_severity' => $this->groupAccidentsBySeverity($accidents),
            'weather_related_accidents' => $this->countWeatherRelatedAccidents($accidents),
            'average_vehicles_per_accident' => $this->calculateAverageVehicles($accidents),
            'recommendations' => $this->generateRecommendations($intersection, $accidents)
        ];

        return $analysis;
    }

    public function identifyHighRiskIntersections(int $minAccidentCount = 3): array
    {
        $allIntersections = $this->intersectionRepository->findMostDangerous(50);
        
        $highRisk = [];
        foreach ($allIntersections as $intersection) {
            if ($intersection->getAccidentCount() >= $minAccidentCount) {
                $analysis = $this->analyzeIntersectionSafety($intersection->getId());
                if ($analysis['safety_score'] < 60) {
                    $highRisk[] = $analysis;
                }
            }
        }

        return $highRisk;
    }

    private function groupAccidentsBySeverity(array $accidents): array
    {
        $severityCount = ['minor' => 0, 'serious' => 0, 'fatal' => 0];
        
        foreach ($accidents as $accident) {
            $severityCount[$accident->getSeverity()]++;
        }
        
        return $severityCount;
    }

    private function countWeatherRelatedAccidents(array $accidents): int
    {
        return count(array_filter($accidents, function($accident) {
            return $accident->isWeatherRelated();
        }));
    }

    private function calculateAverageVehicles(array $accidents): float
    {
        if (empty($accidents)) {
            return 0.0;
        }

        $totalVehicles = array_sum(array_map(function($accident) {
            return $accident->getInvolvedVehicleCount();
        }, $accidents));

        return round($totalVehicles / count($accidents), 1);
    }

    private function generateRecommendations($intersection, array $accidents): array
    {
        $recommendations = [];
        $safetyScore = $intersection->calculateSafetyScore();

        if ($safetyScore < 50) {
            $recommendations[] = "HIGH PRIORITY: Immediate safety measures required";
        }

        if (!$intersection->hasTrafficLights() && count($accidents) > 2) {
            $recommendations[] = "Consider installing traffic lights";
        }

        if ($this->countWeatherRelatedAccidents($accidents) > count($accidents) * 0.3) {
            $recommendations[] = "Improve drainage and anti-skid surface";
        }

        if (count($accidents) > 5) {
            $recommendations[] = "Conduct detailed traffic study";
        }

        if (empty($recommendations)) {
            $recommendations[] = "Current safety measures appear adequate";
        }

        return $recommendations;
    }
}
