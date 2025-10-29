<?php

namespace TrafficSafetyTests\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TrafficSafetyTests\Entity\Accident;

class AccidentTest extends TestCase
{
    public function testSeverityWeight(): void
    {
        $fatal = new Accident('Location', 'fatal', ['car', 'truck']);
        $serious = new Accident('Location', 'serious', ['car']);
        $minor = new Accident('Location', 'minor', ['car']);

        $this->assertEquals(10, $fatal->getSeverityWeight());
        $this->assertEquals(5, $serious->getSeverityWeight());
        $this->assertEquals(2, $minor->getSeverityWeight());
    }

    public function testWeatherRelated(): void
    {
        $rainAccident = new Accident('Location', 'minor', ['car'], 'rain');
        $clearAccident = new Accident('Location', 'minor', ['car'], 'clear');

        $this->assertTrue($rainAccident->isWeatherRelated());
        $this->assertFalse($clearAccident->isWeatherRelated());
    }

    public function testInvolvedVehicleCount(): void
    {
        $multiVehicle = new Accident('Location', 'serious', ['car', 'truck', 'motorcycle']);
        $singleVehicle = new Accident('Location', 'minor', ['car']);

        $this->assertEquals(3, $multiVehicle->getInvolvedVehicleCount());
        $this->assertEquals(1, $singleVehicle->getInvolvedVehicleCount());
    }
}
