<?php
use PHPUnit\Framework\TestCase;
use App\Repository\InMemoryAccidentRepository;
use App\Service\AccidentService;
use App\Service\SimpleCostEstimator;
use App\Factory\AccidentFactory;

final class AccidentServiceTest extends TestCase
{
    public function testTotalEstimation() {
        $repo = new InMemoryAccidentRepository();
        $estimator = new SimpleCostEstimator();
        $service = new AccidentService($repo, $estimator);

        $repo->save(AccidentFactory::create([
            'occurredAt' => '2025-10-28',
            'location' => 'Rákóczu út 3.',
            'type' => 'PDO',
            'cost' => 250.0,
            'roadSegmentId' => 10,
        ]));
        $repo->save(AccidentFactory::create([
            'occurredAt' => '2025-10-29',
            'location' => 'Zsolnay u. 12.',
            'severity' => 'minor',
            'type' => 'Injury',
            'cost' => 150.0,
            'roadSegmentId' => 3,
            'intersectionId' => 7,
        ]));
        $this->assertGreaterThan(0, $service->totalEstimatedCost());
    }
}
