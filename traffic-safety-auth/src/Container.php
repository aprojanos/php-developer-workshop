<?php

declare(strict_types=1);

namespace App;

use AccidentContext\Application\AccidentService;
use AccidentContext\Domain\Service\SimpleCostCalculator;
use AccidentContext\Infrastructure\Repository\Decorator\CachingAccidentRepositoryDecorator;
use AccidentContext\Infrastructure\Repository\PdoAccidentRepository;
use App\Logger\FileLogger;
use App\Security\JwtManager;
use CountermeasureContext\Application\CountermeasureService;
use CountermeasureContext\Infrastructure\Repository\PdoCountermeasureRepository;
use HotspotContext\Application\HotspotService;
use HotspotContext\Infrastructure\Repository\PdoHotspotRepository;
use NotificationContext\Application\NotificationService;
use NotificationContext\Infrastructure\Notifier\FileNotifier;
use ProjectContext\Application\ProjectService;
use ProjectContext\Infrastructure\Repository\PdoProjectRepository;
use RoadNetworkContext\Application\RoadNetworkService;
use RoadNetworkContext\Infrastructure\Repository\PdoIntersectionRepository;
use RoadNetworkContext\Infrastructure\Repository\PdoRoadSegmentRepository;
use SharedKernel\Contract\AccidentRepositoryInterface;
use SharedKernel\Contract\CountermeasureRepositoryInterface;
use SharedKernel\Contract\HotspotRepositoryInterface;
use SharedKernel\Contract\IntersectionRepositoryInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\NotifierInterface;
use SharedKernel\Contract\ProjectRepositoryInterface;
use SharedKernel\Contract\RoadSegmentRepositoryInterface;
use SharedKernel\Contract\UserRepositoryInterface;
use SharedKernel\Domain\Event\InMemoryEventBus;
use UserContext\Application\UserService;
use UserContext\Infrastructure\Repository\PdoUserRepository;
use SharedKernel\Domain\Event\EventBusInterface;

final class Container
{
    private ?\PDO $pdo = null;
    private ?LoggerInterface $logger = null;
    private ?NotifierInterface $notifier = null;
    private ?EventBusInterface $eventBus = null;

    private ?AccidentRepositoryInterface $accidentRepository = null;
    private ?CountermeasureRepositoryInterface $countermeasureRepository = null;
    private ?HotspotRepositoryInterface $hotspotRepository = null;
    private ?ProjectRepositoryInterface $projectRepository = null;
    private ?IntersectionRepositoryInterface $intersectionRepository = null;
    private ?RoadSegmentRepositoryInterface $roadSegmentRepository = null;
    private ?UserRepositoryInterface $userRepository = null;

    private ?AccidentService $accidentService = null;
    private ?HotspotService $hotspotService = null;
    private ?ProjectService $projectService = null;
    private ?CountermeasureService $countermeasureService = null;
    private ?RoadNetworkService $roadNetworkService = null;
    private ?NotificationService $notificationService = null;
    private ?UserService $userService = null;
    private ?JwtManager $jwtManager = null;

    public function __construct(
        private readonly string $projectRoot
    ) {}

    public function boot(): void
    {
        // Intentionally empty: bootstrapping happens lazily.
    }

    public function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_PORT'] ?? '5432',
                $_ENV['DB_NAME'] ?? 'traffic_safety'
            );

            $this->pdo = new \PDO($dsn, $_ENV['DB_USER'] ?? 'postgres', $_ENV['DB_PASSWORD'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        }

        return $this->pdo;
    }

    public function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $logPath = $this->projectRoot . '/storage/logs/api.log';
            $this->logger = new FileLogger($logPath);
        }

        return $this->logger;
    }

    public function getNotifier(): NotifierInterface
    {
        if ($this->notifier === null) {
            $notificationsPath = $this->projectRoot . '/storage/logs/api-notifications.log';
            $this->notifier = new FileNotifier($notificationsPath);
        }

        return $this->notifier;
    }

    public function getEventBus(): EventBusInterface
    {
        if ($this->eventBus === null) {
            $this->eventBus = new InMemoryEventBus();
        }

        return $this->eventBus;
    }

    public function getAccidentRepository(): AccidentRepositoryInterface
    {
        if ($this->accidentRepository === null) {
            $repository = new PdoAccidentRepository($this->getPdo());
            $this->accidentRepository = new CachingAccidentRepositoryDecorator($repository, 30);
        }

        return $this->accidentRepository;
    }

    public function getCountermeasureRepository(): CountermeasureRepositoryInterface
    {
        return $this->countermeasureRepository ??= new PdoCountermeasureRepository($this->getPdo());
    }

    public function getHotspotRepository(): HotspotRepositoryInterface
    {
        return $this->hotspotRepository ??= new PdoHotspotRepository($this->getPdo());
    }

    public function getProjectRepository(): ProjectRepositoryInterface
    {
        return $this->projectRepository ??= new PdoProjectRepository($this->getPdo());
    }

    public function getIntersectionRepository(): IntersectionRepositoryInterface
    {
        return $this->intersectionRepository ??= new PdoIntersectionRepository($this->getPdo());
    }

    public function getRoadSegmentRepository(): RoadSegmentRepositoryInterface
    {
        return $this->roadSegmentRepository ??= new PdoRoadSegmentRepository($this->getPdo());
    }

    public function getUserRepository(): UserRepositoryInterface
    {
        return $this->userRepository ??= new PdoUserRepository($this->getPdo());
    }

    public function getAccidentService(): AccidentService
    {
        if ($this->accidentService === null) {
            $this->accidentService = new AccidentService(
                repository: $this->getAccidentRepository(),
                costCalculator: new SimpleCostCalculator(),
                logger: $this->getLogger(),
                notifier: $this->getNotifier(),
                eventBus: $this->getEventBus()
            );
        }

        return $this->accidentService;
    }

    public function getProjectService(): ProjectService
    {
        return $this->projectService ??= new ProjectService(
            repository: $this->getProjectRepository(),
            logger: $this->getLogger(),
            eventBus: $this->getEventBus(),
        );
    }

    public function getCountermeasureService(): CountermeasureService
    {
        return $this->countermeasureService ??= new CountermeasureService(
            repository: $this->getCountermeasureRepository(),
            logger: $this->getLogger(),
            eventBus: $this->getEventBus(),
        );
    }

    public function getRoadNetworkService(): RoadNetworkService
    {
        return $this->roadNetworkService ??= new RoadNetworkService(
            intersectionRepository: $this->getIntersectionRepository(),
            roadSegmentRepository: $this->getRoadSegmentRepository(),
            logger: $this->getLogger(),
            eventBus: $this->getEventBus(),
        );
    }

    public function getHotspotService(): HotspotService
    {
        if ($this->hotspotService === null) {
            $this->hotspotService = new HotspotService(
                repository: $this->getHotspotRepository(),
                accidentService: $this->getAccidentService(),
                logger: $this->getLogger(),
                eventBus: $this->getEventBus(),
            );
        }

        return $this->hotspotService;
    }

    public function getNotificationService(): NotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = new NotificationService(
                notifier: $this->getNotifier(),
                eventBus: $this->getEventBus(),
            );
        }

        return $this->notificationService;
    }

    public function getUserService(): UserService
    {
        return $this->userService ??= new UserService(
            repository: $this->getUserRepository(),
            logger: $this->getLogger(),
        );
    }

    public function getJwtManager(): JwtManager
    {
        if ($this->jwtManager === null) {
            $secret = $_ENV['JWT_SECRET'] ?? null;
            if ($secret === null || $secret === '') {
                throw new \RuntimeException('JWT_SECRET environment variable must be set.');
            }

            $ttl = (int)($_ENV['JWT_TTL'] ?? 3600);
            $algorithm = $_ENV['JWT_ALGO'] ?? 'HS256';

            $this->jwtManager = new JwtManager(
                secret: $secret,
                algorithm: $algorithm,
                defaultTtlSeconds: $ttl
            );
        }

        return $this->jwtManager;
    }
}

