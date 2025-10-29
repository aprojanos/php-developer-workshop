<?php

namespace TrafficSafetyTests\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TrafficSafetyTests\Entity\RoadSegment;

class RoadSegmentTest extends TestCase
{
    public function testRiskFactorCalculation(): void
    {
        $highway = new RoadSegment('M1', 10.5, 130, 60000, 'highway');
        $urban = new RoadSegment('Main St', 2.5, 50, 15000, 'urban');
        $rural = new RoadSegment('Country Rd', 15.0, 80, 5000, 'rural');

        print "rural: " . $rural->calculateRiskFactor() . " - urban:  " . $urban->calculateRiskFactor() . "\n";
        $this->assertGreaterThan($urban->calculateRiskFactor(), $highway->calculateRiskFactor());
        $this->assertGreaterThan($rural->calculateRiskFactor(), $urban->calculateRiskFactor());
    }

    public function testRiskFactorWithHighTraffic(): void
    {
        $busyRoad = new RoadSegment('Busy Road', 5.0, 70, 55000, 'urban');
        $riskFactor = $busyRoad->calculateRiskFactor();

        $this->assertIsFloat($riskFactor);
        $this->assertGreaterThan(1.0, $riskFactor);
    }

    public function testSettersAndGetters(): void
    {
        $segment = new RoadSegment('Test Road', 10.0, 50, 10000, 'urban');
        $segment->setId(1);
        $segment->setSpeedLimit(60);
        $segment->setTrafficVolume(20000);

        $this->assertEquals(1, $segment->getId());
        $this->assertEquals(60, $segment->getSpeedLimit());
        $this->assertEquals(20000, $segment->getTrafficVolume());
    }
}
