<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AccidentContext\Application\AccidentService;
use AccidentContext\Domain\Service\SimpleCostCalculator;
use AccidentContext\Infrastructure\Repository\PdoAccidentRepository;
use AccidentContext\Infrastructure\Seeder\AccidentSeeder;
use App\Logger\FileLogger;
use Dotenv\Dotenv;
use NotificationContext\Infrastructure\Notifier\FileNotifier;

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

$logger = new FileLogger(__DIR__ . '/../storage/logs/seed.log');
$notifier = new FileNotifier(__DIR__ . '/../storage/logs/seed_notifications.log');
$repository = new PdoAccidentRepository($pdo);
$costCalculator = new SimpleCostCalculator();
$service = new AccidentService($repository, $costCalculator, $logger, $notifier);

$seeder = new AccidentSeeder($service, $pdo);

$options = getopt('', ['pdo::', 'injury::', 'no-purge']);
$pdoCount = isset($options['pdo']) ? max(0, (int)$options['pdo']) : 10;
$injuryCount = isset($options['injury']) ? max(0, (int)$options['injury']) : 20;
$purge = !isset($options['no-purge']);

$seeder->run($pdoCount, $injuryCount, $purge);

$total = $pdoCount + $injuryCount;
echo "Seeded {$total} accidents ({$pdoCount} PDO, {$injuryCount} Injury)." . PHP_EOL;


