<?php

namespace TrafficSafetyTests\Service;

use TrafficSafetyTests\Repository\AccidentRepository;

class StatisticsService
{
    private AccidentRepository $accidentRepository;

    public function __construct(AccidentRepository $accidentRepository)
    {
        $this->accidentRepository = $accidentRepository;
    }

    public function getAccidentStatistics(\DateTime $startDate, \DateTime $endDate): array
    {
        $accidents = $this->accidentRepository->getAccidentsInPeriod($startDate, $endDate);
        
        if (empty($accidents)) {
            return [
                'total_accidents' => 0,
                'message' => 'No accidents found in the specified period'
            ];
        }

        $severityWeights = array_map(function($accident) {
            return $accident->getSeverityWeight();
        }, $accidents);

        $weatherRelated = array_filter($accidents, function($accident) {
            return $accident->isWeatherRelated();
        });

        return [
            'total_accidents' => count($accidents),
            'severity_breakdown' => $this->calculateSeverityBreakdown($accidents),
            'weather_related_percentage' => round(count($weatherRelated) / count($accidents) * 100, 1),
            'average_severity_score' => round(array_sum($severityWeights) / count($severityWeights), 2),
            'total_vehicles_involved' => array_sum(array_map(function($accident) {
                return $accident->getInvolvedVehicleCount();
            }, $accidents)),
            'most_common_severity' => $this->findMostCommonSeverity($accidents)
        ];
    }

    public function calculateMonthlyTrends(int $year): array
    {
        $monthlyData = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $startDate = new \DateTime("{$year}-{$month}-01");
            $endDate = (clone $startDate)->modify('last day of this month');
            
            $monthlyData[$month] = $this->getAccidentStatistics($startDate, $endDate);
        }
        
        return $monthlyData;
    }

    private function calculateSeverityBreakdown(array $accidents): array
    {
        $breakdown = ['minor' => 0, 'serious' => 0, 'fatal' => 0];
        
        foreach ($accidents as $accident) {
            $breakdown[$accident->getSeverity()]++;
        }
        
        // Átalakítás százalékos formátumba
        $total = count($accidents);
        if ($total > 0) {
            foreach ($breakdown as $severity => $count) {
                $breakdown[$severity] = [
                    'count' => $count,
                    'percentage' => round(($count / $total) * 100, 1)
                ];
            }
        }
        
        return $breakdown;
    }

    private function findMostCommonSeverity(array $accidents): string
    {
        if (empty($accidents)) {
            return 'none';
        }

        $severityCount = array_count_values(array_map(function($accident) {
            return $accident->getSeverity();
        }, $accidents));

        arsort($severityCount);
        return array_key_first($severityCount);
    }
}
