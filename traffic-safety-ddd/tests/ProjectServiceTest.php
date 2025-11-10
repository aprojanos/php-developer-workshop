<?php

use App\Factory\AccidentFactory;
use App\Service\ProjectService;
use PHPUnit\Framework\TestCase;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\ProjectRepositoryInterface;
use SharedKernel\Domain\Event\AccidentCreatedEvent;
use SharedKernel\Domain\Event\DomainEventInterface;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\ProjectEvaluatedEvent;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\ProjectStatus;
use SharedKernel\Model\Project;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\ValueObject\TimePeriod;

final class ProjectServiceTest extends TestCase
{
    public function testEvaluateProjectsTriggeredByAccidentCreatedEvent(): void
    {
        $accident = AccidentFactory::create([
            'id' => 205,
            'occurredAt' => '2025-02-01 10:15:00',
            'type' => AccidentType::INJURY->value,
            'severity' => InjurySeverity::SERIOUS->value,
            'cost' => 3200.00,
        ]);

        $project = new Project(
            id: 501,
            countermeasureId: 710,
            hotspotId: 44,
            period: new TimePeriod(
                new \DateTimeImmutable('2025-01-01'),
                new \DateTimeImmutable('2025-12-31')
            ),
            expectedCost: new MonetaryAmount(125000.0),
            actualCost: new MonetaryAmount(118500.0),
            status: ProjectStatus::APPROVED
        );

        /** @var ProjectRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(ProjectRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('all')
            ->willReturn([$project]);

        /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Projects evaluated after accident',
                $this->callback(static function (array $context) use ($accident): bool {
                    return $context['accidentId'] === $accident->id
                        && $context['accidentType'] === $accident->getType()->value
                        && $context['accidentOccurredAt'] === $accident->occurredAt->format('c');
                })
            );

        $eventBus = new InMemoryEventBus();

        new ProjectService($repository, $logger, $eventBus);

        $eventBus->dispatch(new AccidentCreatedEvent($accident));

        $this->assertCount(2, $eventBus->dispatchedEvents);
        $this->assertInstanceOf(AccidentCreatedEvent::class, $eventBus->dispatchedEvents[0]);
        $this->assertInstanceOf(ProjectEvaluatedEvent::class, $eventBus->dispatchedEvents[1]);
        $this->assertSame($project, $eventBus->dispatchedEvents[1]->getProject());
        $this->assertSame($accident, $eventBus->dispatchedEvents[1]->getAccident());
    }
}

final class InMemoryEventBus implements EventBusInterface
{
    /**
     * @var array<class-string<DomainEventInterface>, list<callable>>
     */
    private array $listeners = [];

    /**
     * @var list<DomainEventInterface>
     */
    public array $dispatchedEvents = [];

    public function dispatch(DomainEventInterface $event): void
    {
        $this->dispatchedEvents[] = $event;

        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }
    }

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }
}

