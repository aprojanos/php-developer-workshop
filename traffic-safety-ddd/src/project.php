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

use App\Repository\PdoAccidentRepository;
use App\Service\AccidentService;
use App\Service\SimpleCostCalculator;
use App\Logger\FileLogger;
use App\Notifier\FileNotifier;
use SharedKernel\ValueObject\TimePeriod;
use App\Repository\PdoHotspotRepository;
use SharedKernel\Model\RoadSegment;
use App\Repository\PdoCountermeasureRepository;
use App\Service\CountermeasureService;
use SharedKernel\DTO\CountermeasureHotspotFilterDTO;
use SharedKernel\Enum\TargetType;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use App\Repository\PdoProjectRepository;
use App\Service\ProjectService;
use SharedKernel\Model\Project;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\Enum\ProjectStatus;
use App\Service\NotificationService;
use SharedKernel\Domain\Event\InMemoryEventBus;
/**
 * @template T
 * @param array<int, T> $items
 * @return array<int, T>
 */
function randomSubset(array $items): array
{
    if (empty($items)) {
        return [];
    }

    $count = random_int(1, count($items));
    shuffle($items);

    return array_slice($items, 0, $count);
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

$logger = new FileLogger(__DIR__ . '/storage/logs/app.log');
$notifier = new FileNotifier(__DIR__ . '/storage/logs/notifications.log');
$eventBus = new InMemoryEventBus();
$notificationService = new NotificationService($notifier, $eventBus);
$repo = new PdoAccidentRepository($pdo);
$costCalculator = new SimpleCostCalculator();

$accidentRepository = new PdoAccidentRepository($pdo);
$accidentService = new AccidentService($accidentRepository, $costCalculator, $logger, $notifier, $eventBus);

$hotspotRepository  = new PdoHotspotRepository($pdo);

$countermeasureRepository = new PdoCountermeasureRepository($pdo);
$countermeasureService = new CountermeasureService($countermeasureRepository, $logger, $eventBus);
$projectRepository = new PdoProjectRepository($pdo);
$projectService = new ProjectService($projectRepository, $logger, $eventBus);

echo "\nEvaluating countermeasures for existing hotspots...\n";
$evaluatedHotspots = $hotspotRepository->all();
$existingProjects = $projectRepository->all();
$nextProjectId = empty($existingProjects)
    ? 1
    : (max(array_map(static fn (Project $project) => $project->id, $existingProjects)) + 1);

if (empty($evaluatedHotspots)) {
    echo "No hotspots available for evaluation.\n";
} else {
    foreach ($evaluatedHotspots as $hotspot) {
        $targetType = $hotspot->location instanceof RoadSegment
            ? TargetType::ROAD_SEGMENT
            : TargetType::INTERSECTION;

        $affectedCollisionTypes = match ($targetType) {
            TargetType::ROAD_SEGMENT => randomSubset([
                CollisionType::REAR_END,
                CollisionType::SIDESWIPE,
                CollisionType::HEAD_ON,
                CollisionType::SINGLE_VEHICLE,
                CollisionType::OTHER,
            ]),
            TargetType::INTERSECTION => randomSubset([
                CollisionType::ANGLE,
                CollisionType::SIDE,
                CollisionType::REAR_END,
                CollisionType::HEAD_ON,
            ]),
        };

        $affectedSeverities = $hotspot->riskScore >= 1000
            ? [InjurySeverity::SERIOUS, InjurySeverity::FATAL]
            : [InjurySeverity::MINOR, InjurySeverity::SERIOUS];

        $filter = new CountermeasureHotspotFilterDTO(
            targetType: $targetType,
            affectedCollisionTypes: $affectedCollisionTypes,
            affectedSeverities: $affectedSeverities
        );

        $matchingCountermeasures = $countermeasureService->findForHotspot($filter);
        $topCountermeasures = array_slice($matchingCountermeasures, 0, 3);

        echo "Hotspot {$hotspot->id} ({$targetType->value}) - risk score {$hotspot->riskScore}\n";

        if (empty($topCountermeasures)) {
            echo "  - No matching countermeasures found.\n";
            continue;
        }

        foreach ($topCountermeasures as $index => $countermeasure) {
            $position = $index + 1;
            echo sprintf(
                "  %d. %s (CMF %.2f, status %s)\n",
                $position,
                $countermeasure->name,
                $countermeasure->cmf,
                $countermeasure->lifecycleStatus->value
            );
        }

        $existingHotspotProjects = $projectService->findByHotspot($hotspot->id);
        if (!empty($existingHotspotProjects)) {
            echo "  - Project already exists for this hotspot, skipping creation.\n";
            continue;
        }

        $selectedCountermeasure = $topCountermeasures[random_int(0, count($topCountermeasures) - 1)];

        $statusOptions = [
            ProjectStatus::PROPOSED,
            ProjectStatus::APPROVED,
            ProjectStatus::IMPLEMENTED,
        ];
        $selectedStatus = $statusOptions[array_rand($statusOptions)];

        $expectedCostAmount = random_int(80_000, 350_000);
        $expectedCost = new MonetaryAmount((float) $expectedCostAmount, 'USD');

        $actualCostAmount = in_array($selectedStatus, [ProjectStatus::IMPLEMENTED], true)
            ? $expectedCostAmount * (random_int(90, 120) / 100)
            : 0.0;
        $actualCost = new MonetaryAmount((float) round($actualCostAmount, 2), 'USD');

        $projectPeriod = new TimePeriod(
            startDate: $hotspot->period->startDate,
            endDate: $hotspot->period->endDate
        );

        $project = new Project(
            id: $nextProjectId++,
            countermeasureId: $selectedCountermeasure->id,
            hotspotId: $hotspot->id,
            period: $projectPeriod,
            expectedCost: $expectedCost,
            actualCost: $actualCost,
            status: $selectedStatus
        );

        $projectService->create($project);

        echo sprintf(
            "  - Project %d created using countermeasure %d (%s) with status %s.\n",
            $project->id,
            $selectedCountermeasure->id,
            $selectedCountermeasure->name,
            $selectedStatus->value
        );
    }
}

echo "Done.\n";