<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Logger\FileLogger;
use CountermeasureContext\Application\CountermeasureService;
use CountermeasureContext\Infrastructure\Repository\PdoCountermeasureRepository;
use CountermeasureContext\Infrastructure\Seeder\CountermeasureSeeder;
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

$options = getopt('', ['intersection::', 'road::', 'no-purge']);
$intersectionCount = isset($options['intersection']) ? max(0, (int)$options['intersection']) : 5;
$roadCount = isset($options['road']) ? max(0, (int)$options['road']) : 5;
$purge = !isset($options['no-purge']);

$logger = new FileLogger(__DIR__ . '/../storage/logs/seed_countermeasures.log');
$repository = new PdoCountermeasureRepository($pdo);
$service = new CountermeasureService($repository, $logger);
$seeder = new CountermeasureSeeder($service, $pdo);

$seeder->run($intersectionCount, $roadCount, $purge);

$total = $intersectionCount + $roadCount;
echo "Seeded {$total} countermeasures ({$intersectionCount} intersection, {$roadCount} road segment)." . PHP_EOL;


