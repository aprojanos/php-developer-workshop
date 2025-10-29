<?php

namespace TrafficSafetyTests\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TrafficSafetyTests\Entity\Intersection;
use TrafficSafetyTests\Entity\RoadSegment;

class IntersectionTest extends TestCase
{
    public function testSafetyScoreCalculation(): void
    {
        $intersection = new Intersection('Test Cross', 'cross', false);
        $intersection->setId(1);

        // Kezdeti pontszám teszt
        $this->assertEquals(90, $intersection->calculateSafetyScore());

        // Balesetek hatása
        $intersection->incrementAccidentCount();
        $intersection->incrementAccidentCount();
        $this->assertEquals(80, $intersection->calculateSafetyScore());

        // Közlekedési lámpa hatása
        $intersection->setHasTrafficLights(true);
        $this->assertEquals(100, $intersection->calculateSafetyScore());
    }

    public function testAddConnectedSegment(): void
    {
        $intersection = new Intersection('Roundabout', 'roundabout', true);
        $segment1 = new RoadSegment('Road 1', 1.0, 50, 10000, 'urban');
        $segment2 = new RoadSegment('Road 2', 1.0, 50, 10000, 'urban');

        $intersection->addConnectedSegment($segment1);
        $intersection->addConnectedSegment($segment2);

        $this->assertCount(2, $intersection->getConnectedSegments());
    }

    public function testIntersectionTypes(): void
    {
        $roundabout = new Intersection('R1', 'roundabout', true);
        $cross = new Intersection('C1', 'cross', false);
        $tJunction = new Intersection('T1', 't-junction', false);

        $this->assertGreaterThan(
            $cross->calculateSafetyScore(), 
            $roundabout->calculateSafetyScore()
        );
    }
}
