<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Logger\FileLogger;
use App\Repository\PdoAccidentRepository;
use App\Repository\PdoHotspotRepository;
use App\Seeder\HotspotSeeder;
use App\Service\AccidentService;
use App\Service\HotspotService;
use App\Service\SimpleCostCalculator;
use Dotenv\Dotenv;

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '54321';
$dbName = $_ENV['DB_NAME'] ?? 'traffic_safety';
$dbUser = $_ENV['DB_USER'] ?? 'postgres';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

$dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
$pdo = new PDO($dsn, $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$options = getopt('', ['count::', 'intersection-share::', 'no-purge']);
$count = isset($options['count']) ? max(1, (int)$options['count']) : 10;
$intersectionShare = isset($options['intersection-share'])
    ? max(0.0, min(1.0, (float)$options['intersection-share']))
    : 0.5;
$purge = !isset($options['no-purge']);

$accidentRepository = new PdoAccidentRepository($pdo);
$accidentService = new AccidentService($accidentRepository, new SimpleCostCalculator());

$hotspotLogger = new FileLogger(__DIR__ . '/storage/logs/seed_hotspots.log');
$hotspotRepository = new PdoHotspotRepository($pdo);
$hotspotService = new HotspotService($hotspotRepository, $accidentService, $hotspotLogger);

$seeder = new HotspotSeeder($hotspotService, $pdo);
$seeder->run($count, $purge, $intersectionShare);

echo "Seeded {$count} hotspots." . PHP_EOL;


