<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Repository\PdoHotspotRepository;
use App\Repository\PdoIntersectionRepository;
use App\Repository\PdoRoadSegmentRepository;
use App\Service\HotspotService;
use SharedKernel\Model\Hotspot;
use SharedKernel\Enum\LocationType;
use SharedKernel\DTO\HotspotScreeningDTO;
use SharedKernel\ValueObject\TimePeriod;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\HotspotStatus;
use App\Factory\HotspotFactory;
use App\Service\AccidentService;
use App\Logger\FileLogger;
use App\Notifier\FileNotifier;
use SharedKernel\Domain\Event\InMemoryEventBus;
use SharedKernel\Model\RoadSegment;
use SharedKernel\Model\Intersection;
use App\Repository\PdoAccidentRepository;
use App\Service\SimpleCostCalculator;

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '5432';
$dbName = $_ENV['DB_NAME'] ?? 'traffic_safety';
$dbUser = $_ENV['DB_USER'] ?? 'postgres';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

$dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
$pdo = new PDO($dsn, $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$accidentRepository = new PdoAccidentRepository($pdo);
$costCalculator = new SimpleCostCalculator();
$logger = new FileLogger(__DIR__ . '/../storage/logs/screening.log');
$notifier = new FileNotifier(__DIR__ . '/../storage/logs/screening.log');
$eventBus = new InMemoryEventBus();
$accidentService = new AccidentService($accidentRepository, $costCalculator, $logger, $notifier, $eventBus);

$logger = new FileLogger(__DIR__ . '/../storage/logs/screening.log');
$notifier = new FileNotifier(__DIR__ . '/../storage/logs/screening.log');
$eventBus = new InMemoryEventBus();

// screening for hotspots
$hotspotRepository  = new PdoHotspotRepository($pdo);
$hotspotService = new HotspotService($hotspotRepository, $accidentService, $logger);
$intersectionRepository = new PdoIntersectionRepository($pdo);
$roadSegmentRepository = new PdoRoadSegmentRepository($pdo);

$existingHotspots = $hotspotRepository->all();
$nextHotspotId = empty($existingHotspots)
    ? 1
    : (max(array_map(static fn (Hotspot $hotspot) => $hotspot->id, $existingHotspots)) + 1);

// perform screening for hotspots and create hotspots for the to 1 by location type

foreach (LocationType::cases() as $locationType) {
    echo "Screening for {$locationType->value} hotspots...\n";
    $screeningDto = new HotspotScreeningDTO(
        locationType: $locationType,
        threshold: 1000,
        period: new TimePeriod(
            startDate: new \DateTimeImmutable('2025-10-01'),
            endDate: new \DateTimeImmutable('2025-10-31')
        ),
    );
    $possibleHotspots = $hotspotService->screeningForHotspots($screeningDto);
    echo "\tFound " . count($possibleHotspots) . " possible {$locationType->value} hotspots.\n";
    $first = true;
    foreach ($possibleHotspots as $possibleHotspot) {
        echo  "\tPossible hotspot: " . json_encode($possibleHotspot) . "\n";
        if ($first) {
            $first = false;
            $locationId = $possibleHotspot['locationId'];

            $alreadyExists = array_filter(
                $existingHotspots,
                static function (Hotspot $hotspot) use ($locationType, $locationId): bool {
                    return match ($locationType) {
                        LocationType::ROADSEGMENT => $hotspot->location instanceof RoadSegment
                            && $hotspot->location->id === $locationId,
                        LocationType::INTERSECTION => $hotspot->location instanceof Intersection
                            && $hotspot->location->id === $locationId,
                    };
                }
            );

            if (!empty($alreadyExists)) {
                echo "\tHotspot already exists for location {$locationId}, skipping creation.\n";
                continue;
            }

            $location = match ($locationType) {
                LocationType::ROADSEGMENT => $roadSegmentRepository->findById($locationId),
                LocationType::INTERSECTION => $intersectionRepository->findById($locationId),
            };

            if ($location === null) {
                echo "\tUnable to load {$locationType->value} {$locationId}; hotspot not created.\n";
                continue;
            }

            $period = $screeningDto->period ?? new TimePeriod(
                startDate: new \DateTimeImmutable('-1 year'),
                endDate: new \DateTimeImmutable(),
            );

            $injuryCrashes = (int) round($possibleHotspot['accidentCount'] * 0.3);
            $injuryCrashes = min($injuryCrashes, $possibleHotspot['accidentCount']);
            $pdoCrashes = max(0, $possibleHotspot['accidentCount'] - $injuryCrashes);

            if ($pdoCrashes === 0 && $injuryCrashes === 0) {
                $pdoCrashes = $possibleHotspot['accidentCount'];
            }

            $expectedCrashes = max(1, round($possibleHotspot['accidentCount'] * 0.8, 2));

            $hotspotData = [
                'id' => $nextHotspotId++,
                'location' => $location,
                'period_start' => $period->startDate->format(DATE_ATOM),
                'period_end' => $period->endDate->format(DATE_ATOM),
                'observed_crashes' => [
                    AccidentType::PDO->value => $pdoCrashes,
                    AccidentType::INJURY->value => $injuryCrashes,
                ],
                'expected_crashes' => $expectedCrashes,
                'risk_score' => (float) $possibleHotspot['score'],
                'status' => HotspotStatus::OPEN->value,
                'screening_parameters' => [
                    'source' => 'index-demo',
                    'threshold' => $screeningDto->threshold,
                    'accidentCount' => $possibleHotspot['accidentCount'],
                    'calculatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'locationType' => $locationType->value,
                ],
            ];

            $newHotspot = HotspotFactory::create($hotspotData);
            $hotspotService->create($newHotspot);
            $existingHotspots[] = $newHotspot;

            echo "\tHotspot created with ID {$newHotspot->id}\n";
        }
    }
}

echo "Done.\n";
