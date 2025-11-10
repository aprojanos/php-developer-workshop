<?php

use App\Factory\AccidentFactory;
use App\Service\ProjectService;
use PHPUnit\Framework\TestCase;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\ProjectRepositoryInterface;
use SharedKernel\Domain\Event\AccidentCreatedEvent;
use SharedKernel\Domain\Event\ProjectApprovedEvent;
use SharedKernel\Domain\Event\ProjectEvaluatedEvent;
use SharedKernel\Domain\Event\ProjectImplementedEvent;
use SharedKernel\Domain\Event\ProjectProposedEvent;
use SharedKernel\Domain\Event\InMemoryEventBus;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\ProjectStatus;
use SharedKernel\Model\Project;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\ValueObject\TimePeriod;

final class ProjectServiceTest extends TestCase
{
    public function testCreateDispatchesProjectProposedEvent(): void
    {
        $project = $this->createProject(1001, ProjectStatus::PROPOSED);

        /** @var ProjectRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(ProjectRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($project);

        /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Project created', $this->isType('array'));

        $eventBus = new InMemoryEventBus();

        $service = new ProjectService($repository, $logger, $eventBus);
        $service->create($project);

        $this->assertCount(1, $eventBus->dispatchedEvents);
        $event = $eventBus->dispatchedEvents[0];
        $this->assertInstanceOf(ProjectProposedEvent::class, $event);
        /** @var ProjectProposedEvent $event */
        $this->assertSame($project, $event->getProject());
    }

    public function testTransitionStatusDispatchesApprovedEvent(): void
    {
        $existingProject = $this->createProject(2002, ProjectStatus::PROPOSED);

        /** @var ProjectRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(ProjectRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($existingProject->id)
            ->willReturn($existingProject);
        $repository->expects($this->once())
            ->method('update')
            ->with($this->callback(static function (Project $project): bool {
                return $project->status === ProjectStatus::APPROVED;
            }));

        /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Project status transitioned', $this->isType('array'));

        $eventBus = new InMemoryEventBus();

        $service = new ProjectService($repository, $logger, $eventBus);
        $result = $service->transitionStatus($existingProject->id, ProjectStatus::APPROVED);

        $this->assertSame(ProjectStatus::APPROVED, $result->status);
        $this->assertCount(1, $eventBus->dispatchedEvents);
        $event = $eventBus->dispatchedEvents[0];
        $this->assertInstanceOf(ProjectApprovedEvent::class, $event);
        /** @var ProjectApprovedEvent $event */
        $this->assertSame($result, $event->getProject());
    }

    public function testTransitionStatusDispatchesImplementedEvent(): void
    {
        $existingProject = $this->createProject(3003, ProjectStatus::APPROVED);

        /** @var ProjectRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(ProjectRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with($existingProject->id)
            ->willReturn($existingProject);
        $repository->expects($this->once())
            ->method('update')
            ->with($this->callback(static function (Project $project): bool {
                return $project->status === ProjectStatus::IMPLEMENTED;
            }));

        /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Project status transitioned', $this->isType('array'));

        $eventBus = new InMemoryEventBus();

        $service = new ProjectService($repository, $logger, $eventBus);
        $result = $service->transitionStatus($existingProject->id, ProjectStatus::IMPLEMENTED);

        $this->assertSame(ProjectStatus::IMPLEMENTED, $result->status);
        $this->assertCount(1, $eventBus->dispatchedEvents);
        $event = $eventBus->dispatchedEvents[0];
        $this->assertInstanceOf(ProjectImplementedEvent::class, $event);
        /** @var ProjectImplementedEvent $event */
        $this->assertSame($result, $event->getProject());
    }

    public function testEvaluateProjectsTriggeredByAccidentCreatedEvent(): void
    {
        $accident = AccidentFactory::create([
            'id' => 205,
            'occurredAt' => '2025-02-01 10:15:00',
            'type' => AccidentType::INJURY->value,
            'severity' => InjurySeverity::SERIOUS->value,
            'cost' => 3200.00,
        ]);

        $project = $this->createProject(501, ProjectStatus::APPROVED);

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

        $service = new ProjectService($repository, $logger, $eventBus);
        $this->assertInstanceOf(ProjectService::class, $service);

        $eventBus->dispatch(new AccidentCreatedEvent($accident));

        $this->assertCount(2, $eventBus->dispatchedEvents);
        $this->assertInstanceOf(AccidentCreatedEvent::class, $eventBus->dispatchedEvents[0]);
        $event = $eventBus->dispatchedEvents[1];
        $this->assertInstanceOf(ProjectEvaluatedEvent::class, $event);
        /** @var ProjectEvaluatedEvent $event */
        $this->assertSame($project, $event->getProject());
        $this->assertSame($accident, $event->getAccident());
    }

    private function createProject(int $id, ProjectStatus $status): Project
    {
        return new Project(
            id: $id,
            countermeasureId: 700,
            hotspotId: 70,
            period: new TimePeriod(
                new \DateTimeImmutable('2025-01-01'),
                new \DateTimeImmutable('2025-12-31')
            ),
            expectedCost: new MonetaryAmount(100000.0),
            actualCost: new MonetaryAmount(95000.0),
            status: $status
        );
    }
}


