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
use App\Factory\AccidentFactory;
use App\Repository\PdoAccidentRepository;
use App\Service\AccidentService;
use App\Service\SimpleCostCalculator;
use App\Decorator\CachingAccidentRepositoryDecorator;
use App\Report\CsvReportGenerator;
use App\Logger\FileLogger;
use App\Notifier\FileNotifier;

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
$serviceSimple = new AccidentService($repo, $costCalculator, $logger, $notifier);

$samples = [
    [
        'occurredAt' => '2025-10-28',
        'location' => 'Rákóczu út 3.',
        'type' => 'PDO',
        'cost' => 250.0,
        'roadSegmentId' => 10,
    ],
    [
        'occurredAt' => '2025-10-29',
        'location' => 'Zsolnay u. 12.',
        'severity' => 'minor',
        'type' => 'Injury',
        'cost' => 150.0,
        'roadSegmentId' => 3,
        'intersectionId' => 7,
    ],
    [
        'occurredAt' => '2025-10-31',
        'location' => 'Mártírok útja 30',
        'severity' => 'severe',
        'type' => 'Injury',
        'cost' => 400.0,
        'intersectionId' => 12,
    ]
];
foreach($samples as $sample) {
    $accident = AccidentFactory::create($sample);
    $serviceSimple->create($accident);
}

echo "Using SimpleCostEstimator:\n";
echo "Total estimated cost: " . $serviceSimple->totalEstimatedCost() . "\n\n";

// Now use Advanced estimator
$repo2 = new PdoAccidentRepository($pdo);
// copy items from previous repo for demo
foreach ($repo->all() as $a) {
    $repo2->save($a);
}
$serviceAdv = new AccidentService($repo2, $costCalculator);

echo "Using AdvancedCostEstimator:\n";
echo "Total estimated cost: " . $serviceAdv->totalEstimatedCost() . "\n\n";

// Demonstrate decorator caching
$cachingRepo = new CachingAccidentRepositoryDecorator($repo2, 30);
echo "Decorator demo - first call\n";
echo "count=" . count($cachingRepo->all()) . "\n";
echo "Decorator demo - second call\n";
echo "count=" . count($cachingRepo->all()) . "\n\n";

// Generate CSV report
$csvGen = new CsvReportGenerator($repo2);
$csv = $csvGen->generate();
$folder = __DIR__ . '/storage/export/';
@mkdir($folder, 0755, true);
$file = $folder . 'accidents.csv';
file_put_contents($file, $csv);
echo "Exported refactored CSV to: {$file}\n";

echo "Done.\n";
