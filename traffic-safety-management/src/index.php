<?php

// Demo script for the refactored code
require_once __DIR__ . '/../vendor/autoload.php';
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
use App\Repository\InMemoryAccidentRepository;
use App\Repository\FileAccidentRepository;
use App\Service\AccidentService;
use App\Service\SimpleCostEstimator;
use App\Service\AdvancedCostEstimator;
use App\Decorator\CachingAccidentRepositoryDecorator;
use App\Report\CsvReportGenerator;
use App\Logger\FileLogger;
use App\Notifier\FileNotifier;

$logger = new FileLogger(__DIR__ . '/storage/logs/app.log');
// $notifier = new MailNotifier('safety@example.com', 'New accident notification');
$notifier = new FileNotifier(__DIR__ . '/storage/logs/notifications.log');
$repo = new FileAccidentRepository(__DIR__ . '/storage/data/accidents.csv');
// $repo = new InMemoryAccidentRepository();
$estimatorSimple = new SimpleCostEstimator();
$serviceSimple = new AccidentService($repo, $estimatorSimple, $logger, $notifier);

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
$repo2 = new InMemoryAccidentRepository();
// copy items from previous repo for demo
foreach ($repo->all() as $a) {
    $repo2->save($a);
}
$estimatorAdv = new AdvancedCostEstimator(1200.0);
$serviceAdv = new AccidentService($repo2, $estimatorAdv);

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
