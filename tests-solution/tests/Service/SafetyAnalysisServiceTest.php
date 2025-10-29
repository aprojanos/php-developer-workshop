<?php

namespace TrafficSafetyTests\Tests\Service;

use PHPUnit\Framework\TestCase;
use TrafficSafetyTests\Service\SafetyAnalysisService;
use TrafficSafetyTests\Repository\AccidentRepository;
use TrafficSafetyTests\Repository\IntersectionRepository;
use TrafficSafetyTests\Entity\Intersection;
use TrafficSafetyTests\Entity\Accident;
use PDO;

class SafetyAnalysisServiceTest extends TestCase
{
    private SafetyAnalysisService $service;
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Táblák létrehozása
        $this->database->exec("
            CREATE TABLE intersections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                intersection_type TEXT NOT NULL,
                has_traffic_lights BOOLEAN NOT NULL,
                accident_count INTEGER DEFAULT 0
            )
        ");

        $this->database->exec("
            CREATE TABLE accidents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                occurred_at DATETIME NOT NULL,
                location TEXT NOT NULL,
                severity TEXT NOT NULL,
                involved_vehicles TEXT NOT NULL,
                weather_conditions TEXT,
                intersection_id INTEGER,
                road_segment_id INTEGER
            )
        ");

        $accidentRepo = new AccidentRepository($this->database);
        $intersectionRepo = new IntersectionRepository($this->database);
        
        $this->service = new SafetyAnalysisService($accidentRepo, $intersectionRepo);

        // Tesztadatok
        $intersection = new Intersection('Danger Cross', 'cross', false);
        $intersectionRepo->save($intersection);

        $accident1 = new Accident('Danger Cross', 'minor', ['car'], 'rain', 1);
        $accident2 = new Accident('Danger Cross', 'serious', ['car', 'truck'], null, 1);
        
        $accidentRepo->save($accident1);
        $accidentRepo->save($accident2);
    }

    public function testAnalyzeIntersectionSafety(): void
    {
        $analysis = $this->service->analyzeIntersectionSafety(1);

        $this->assertEquals('Danger Cross', $analysis['intersection_name']);
        $this->assertIsInt($analysis['safety_score']);
        $this->assertEquals(2, $analysis['total_accidents']);
        $this->assertArrayHasKey('minor', $analysis['accidents_by_severity']);
        $this->assertArrayHasKey('serious', $analysis['accidents_by_severity']);
        $this->assertIsArray($analysis['recommendations']);
    }

    public function testAnalyzeNonExistentIntersection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->analyzeIntersectionSafety(999);
    }

    public function testIdentifyHighRiskIntersections(): void
    {
        // További tesztadatok beszúrása magas kockázatú kereszteződésekhez
        $intersectionRepo = new IntersectionRepository($this->database);
        
        $dangerousIntersection = new Intersection('Very Dangerous', 'cross', false);
        $dangerousIntersection->incrementAccidentCount();
        $dangerousIntersection->incrementAccidentCount();
        $dangerousIntersection->incrementAccidentCount();
        $intersectionRepo->save($dangerousIntersection);

        $highRisk = $this->service->identifyHighRiskIntersections(3);
        
        $this->assertIsArray($highRisk);
    }
}
