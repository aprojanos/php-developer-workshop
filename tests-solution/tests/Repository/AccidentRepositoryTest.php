<?php

namespace TrafficSafetyTests\Tests\Repository;

use PHPUnit\Framework\TestCase;
use TrafficSafetyTests\Repository\AccidentRepository;
use TrafficSafetyTests\Entity\Accident;
use PDO;

class AccidentRepositoryTest extends TestCase
{
    private PDO $database;
    private AccidentRepository $repository;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Teszt tábla létrehozása
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

        $this->repository = new AccidentRepository($this->database);
    }

    public function testSaveAndFindAccident(): void
    {
        $accident = new Accident(
            'Main Street Cross',
            'minor',
            ['car', 'bicycle'],
            'rain',
            1,
            2
        );

        $saveResult = $this->repository->save($accident);
        $this->assertTrue($saveResult);

        $foundAccident = $this->repository->findById(1);
        $this->assertNotNull($foundAccident);
        $this->assertEquals('Main Street Cross', $foundAccident->getLocation());
        $this->assertEquals('minor', $foundAccident->getSeverity());
    }

    public function testFindByIntersection(): void
    {
        // Tesztadatok beszúrása
        $accident1 = new Accident('Loc1', 'minor', ['car'], null, 1);
        $accident2 = new Accident('Loc2', 'serious', ['car'], null, 1);
        $accident3 = new Accident('Loc3', 'minor', ['car'], null, 2);

        $this->repository->save($accident1);
        $this->repository->save($accident2);
        $this->repository->save($accident3);

        $intersectionAccidents = $this->repository->findByIntersection(1);
        $this->assertCount(2, $intersectionAccidents);
    }

    public function testFindBySeverity(): void
    {
        $accident1 = new Accident('Loc1', 'minor', ['car']);
        $accident2 = new Accident('Loc2', 'serious', ['car']);
        $accident3 = new Accident('Loc3', 'minor', ['car']);

        $this->repository->save($accident1);
        $this->repository->save($accident2);
        $this->repository->save($accident3);

        $minorAccidents = $this->repository->findBySeverity('minor');
        $this->assertCount(2, $minorAccidents);
    }
}
