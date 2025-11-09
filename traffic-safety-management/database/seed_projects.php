<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Seeder\ProjectSeeder;
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

$options = getopt('', ['count::', 'no-purge']);
$count = isset($options['count']) ? (int)$options['count'] : 5;
$purge = !isset($options['no-purge']);

$seeder = new ProjectSeeder($pdo);
if ($purge) {
    $seeder->purge();
}
if ($count > 0) {
    $seeder->run($count, $purge);
}

echo "Seeded {$count} projects." . PHP_EOL;
