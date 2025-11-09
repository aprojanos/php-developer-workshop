<?php

// Demo script for the refactored code
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file if it exists (optional for Docker)
use Dotenv\Dotenv;

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// // Minimal PSR-4 autoloader for the App\ namespace
// spl_autoload_register(function ($class) {
//     $prefix = 'App\\';
//     $base_dir = __DIR__ . '/';
//     // does the class use the namespace prefix?
//     $len = strlen($prefix);
//     if (strncmp($prefix, $class, $len) !== 0) {
//         return;
//     }
//     // get the relative class name
//     $relative_class = substr($class, $len);
//     $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
//     if (file_exists($file)) {
//         require $file;
//     }
// });

// Use declarations
use App\Repository\PdoAccidentRepository;
use App\Service\AccidentService;
use App\Service\SimpleCostCalculator;
use App\Decorator\CachingAccidentRepositoryDecorator;
use App\Report\CsvReportGenerator;
use App\Logger\FileLogger;
use App\Notifier\FileNotifier;
use App\DTO\AccidentSearchDTO;
use App\ValueObject\TimePeriod;
use App\DTO\AccidentLocationDTO;
use App\Enum\LocationType;
use App\Service\HotspotService;
use App\Repository\PdoHotspotRepository;
use App\DTO\HotspotScreeningDTO;
use App\Model\Intersection;
use App\Model\RoadSegment;
use App\Model\Hotspot;
use App\Repository\PdoIntersectionRepository;
use App\Repository\PdoRoadSegmentRepository;
use App\Enum\HotspotStatus;
use App\Enum\AccidentType;
use App\Factory\HotspotFactory;

// Create PostgreSQL PDO connection
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

$logger = new FileLogger(__DIR__ . '/storage/logs/app.log');
$notifier = new FileNotifier(__DIR__ . '/storage/logs/notifications.log');
$repo = new PdoAccidentRepository($pdo);
$costCalculator = new SimpleCostCalculator();

$accidentRepository = new PdoAccidentRepository($pdo);
$accidentService = new AccidentService($accidentRepository, $costCalculator);

echo "Using costCalculator:\n";
echo "Total estimated cost: " . $accidentService->totalEstimatedCost() . "\n\n";

// Demonstrate decorator caching
$cachingRepo = new CachingAccidentRepositoryDecorator($accidentRepository, 30);
echo "Decorator demo - first call\n";
echo "count=" . count($cachingRepo->all()) . "\n";
echo "Decorator demo - second call\n";
echo "count=" . count($cachingRepo->all()) . "\n\n";

// Generate CSV report
$csvGen = new CsvReportGenerator($accidentRepository);
$csv = $csvGen->generate();
$folder = __DIR__ . '/storage/export/';
@mkdir($folder, 0755, true);
$file = $folder . 'accidents.csv';
file_put_contents($file, $csv);
echo "Exported refactored CSV to: {$file}\n";


// performing a search
echo "Performing a search...\n";
$searchDTO = new AccidentSearchDTO(
    occurredAtInterval: new TimePeriod(
        startDate: new \DateTimeImmutable('2025-10-01'),
        endDate: new \DateTimeImmutable('2025-10-31')
    ),
    location: null
);
$accidents = $accidentService->search($searchDTO);
echo "Found " . count($accidents) . " accidents.\n";
foreach ($accidents as $accident) {
    echo $accident->id . " - " . $accident->occurredAt->format('Y-m-d') . " - " . $accident->getType()->value . " - " . $accident->location->latitude . " - " . $accident->location->longitude . " - " . $accident->location->distanceFromStart . "\n";
    echo "  - Severity: " . $accident->getSeverityLabel() . "\n";
    echo "  - Cost: " . $accident->cost . "\n";
    echo "  - Road Segment ID: " . $accident->location->getRoadSegmentId() . "\n";
    echo "  - Intersection ID: " . $accident->location->getIntersectionId() . "\n";
    echo "  - Distance from start: " . $accident->location->distanceFromStart . "\n";
    echo "\n";
}

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
    echo "Found " . count($possibleHotspots) . " possible {$locationType->value} hotspots.\n";
    $first = true;
    foreach ($possibleHotspots as $possibleHotspot) {
        echo implode(' - ', $possibleHotspot) . "\n";
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
                echo "Hotspot already exists for location {$locationId}, skipping creation.\n";
                continue;
            }

            $location = match ($locationType) {
                LocationType::ROADSEGMENT => $roadSegmentRepository->findById($locationId),
                LocationType::INTERSECTION => $intersectionRepository->findById($locationId),
            };

            if ($location === null) {
                echo "Unable to load {$locationType->value} {$locationId}; hotspot not created.\n";
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

            echo "Hotspot created with ID {$newHotspot->id}\n";
        }
    }
}


echo "Done.\n";
