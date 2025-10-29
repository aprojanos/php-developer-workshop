<?php

namespace TrafficSafetyTests\Tests\Service;

use PHPUnit\Framework\TestCase;
use TrafficSafetyTests\Service\SafetyAnalysisService;
use TrafficSafetyTests\Repository\AccidentRepository;
use TrafficSafetyTests\Repository\IntersectionRepository;
use TrafficSafetyTests\Entity\Intersection;
use TrafficSafetyTests\Entity\Accident;

class SafetyAnalysisServiceMockTest extends TestCase
{
    private SafetyAnalysisService $service;
    private $accidentRepositoryMock;
    private $intersectionRepositoryMock;

    protected function setUp(): void
    {
        // Mock repository-k létrehozása
        $this->accidentRepositoryMock = $this->createMock(AccidentRepository::class);
        $this->intersectionRepositoryMock = $this->createMock(IntersectionRepository::class);
        
        $this->service = new SafetyAnalysisService(
            $this->accidentRepositoryMock,
            $this->intersectionRepositoryMock
        );
    }

    public function testAnalyzeIntersectionSafetyWithMock(): void
    {
        // Mock intersection létrehozása
        $intersection = new Intersection('Test Cross', 'cross', false);
        $intersection->setId(1);
        
        // Mock accidents létrehozása
        $accident1 = new Accident('Test Location', 'minor', ['car'], 'rain', 1);
        $accident2 = new Accident('Test Location', 'serious', ['car', 'truck'], null, 1);

        // Mock repository-k viselkedésének beállítása
        $this->intersectionRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($intersection);

        $this->accidentRepositoryMock
            ->expects($this->once())
            ->method('findByIntersection')
            ->with(1)
            ->willReturn([$accident1, $accident2]);

        // Teszt végrehajtása
        $analysis = $this->service->analyzeIntersectionSafety(1);
        // Assert-ek
        $this->assertEquals('Test Cross', $analysis['intersection_name']);
        $this->assertEquals(2, $analysis['total_accidents']);
        $this->assertEquals(1, $analysis['accidents_by_severity']['minor']);
        $this->assertEquals(1, $analysis['accidents_by_severity']['serious']);
        $this->assertContains('Consider installing traffic lights', $analysis['recommendations']);
    }

    public function testAnalyzeNonExistentIntersectionWithMock(): void
    {
        // Mock repository viselkedés beállítása (null-t ad vissza)
        $this->intersectionRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        // Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Intersection not found with ID: 999');

        $this->service->analyzeIntersectionSafety(999);
    }

    public function testIdentifyHighRiskIntersectionsWithMock(): void
    {
        // Mock intersections létrehozása
        $dangerousIntersection = new Intersection('Danger Cross', 'cross', false);
        $dangerousIntersection->setId(1);
        $dangerousIntersection->incrementAccidentCount();
        $dangerousIntersection->incrementAccidentCount();
        $dangerousIntersection->incrementAccidentCount();

        $safeIntersection = new Intersection('Safe Roundabout', 'roundabout', true);
        $safeIntersection->setId(2);

        // Mock accidents a dangerous intersection-hoz
        $accidents = [
            new Accident('Location', 'serious', ['car'], null, 1),
            new Accident('Location', 'fatal', ['car', 'truck'], 'rain', 1),
            new Accident('Location', 'minor', ['car'], null, 1)
        ];

        // Mock repository-k viselkedésének beállítása
        $this->intersectionRepositoryMock
            ->expects($this->once())
            ->method('findMostDangerous')
            ->with(50)
            ->willReturn([$dangerousIntersection, $safeIntersection]);

        $this->intersectionRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($dangerousIntersection);

        $this->accidentRepositoryMock
            ->expects($this->once())
            ->method('findByIntersection')
            ->with(1)
            ->willReturn($accidents);

        // Teszt végrehajtása
        $highRisk = $this->service->identifyHighRiskIntersections(3);

        // Assert-ek
        $this->assertCount(1, $highRisk);
        $this->assertEquals('Danger Cross', $highRisk[0]['intersection_name']);
        $this->assertLessThan(60, $highRisk[0]['safety_score']);
    }

    public function testSafetyAnalysisWithNoAccidents(): void
    {
        // Mock intersection létrehozása
        $intersection = new Intersection('Safe Intersection', 'roundabout', true);
        $intersection->setId(1);

        // Mock repository-k viselkedésének beállítása
        $this->intersectionRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($intersection);

        $this->accidentRepositoryMock
            ->expects($this->once())
            ->method('findByIntersection')
            ->with(1)
            ->willReturn([]);

        // Teszt végrehajtása
        $analysis = $this->service->analyzeIntersectionSafety(1);

        // Assert-ek
        $this->assertEquals(0, $analysis['total_accidents']);
        $this->assertEquals(0, $analysis['weather_related_accidents']);
        $this->assertEquals(0.0, $analysis['average_vehicles_per_accident']);
        $this->assertContains('Current safety measures appear adequate', $analysis['recommendations']);
    }

    public function testSafetyAnalysisWithWeatherRelatedAccidents(): void
    {
        // Mock intersection létrehozása
        $intersection = new Intersection('Rainy Intersection', 'cross', false);
        $intersection->setId(1);

        // Mock accidents - mind időjárás miatti
        $accidents = [
            new Accident('Location', 'minor', ['car'], 'rain', 1),
            new Accident('Location', 'serious', ['car'], 'snow', 1),
            new Accident('Location', 'minor', ['car'], 'fog', 1)
        ];

        // Mock repository-k viselkedésének beállítása
        $this->intersectionRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($intersection);

        $this->accidentRepositoryMock
            ->expects($this->once())
            ->method('findByIntersection')
            ->with(1)
            ->willReturn($accidents);

        // Teszt végrehajtása
        $analysis = $this->service->analyzeIntersectionSafety(1);

        // Assert-ek
        $this->assertEquals(3, $analysis['weather_related_accidents']);
        $this->assertContains('Improve drainage and anti-skid surface', $analysis['recommendations']);
    }
}
