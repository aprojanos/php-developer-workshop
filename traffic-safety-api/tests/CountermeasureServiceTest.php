<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use AccidentContext\Domain\Factory\AccidentFactory;
use CountermeasureContext\Application\CountermeasureService;
use SharedKernel\Contract\CountermeasureRepositoryInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Domain\Event\ProjectEvaluatedEvent;
use SharedKernel\Domain\Event\InMemoryEventBus;
use SharedKernel\DTO\CountermeasureHotspotFilterDTO;
use CountermeasureContext\Domain\Factory\CountermeasureFactory;
use SharedKernel\Model\Countermeasure;
use SharedKernel\Model\Project;
use SharedKernel\Enum\TargetType;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\LifecycleStatus;
use SharedKernel\Enum\ProjectStatus;
use SharedKernel\Enum\AccidentType;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\ValueObject\TimePeriod;

final class CountermeasureServiceTest extends TestCase
{
    public function testCreatePersistsCountermeasureAndLogs(): void
    {
        $countermeasure = $this->createCountermeasure(['id' => 101]);

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($countermeasure);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Countermeasure created',
                $this->callback(static function (array $context) use ($countermeasure): bool {
                    return $context['id'] === $countermeasure->id
                        && $context['name'] === $countermeasure->name
                        && $context['targetType'] === $countermeasure->getTargetType()->value
                        && $context['lifecycleStatus'] === $countermeasure->lifecycleStatus->value;
                })
            );

        $service = new CountermeasureService($repository, $logger);
        $service->create($countermeasure);
    }

    public function testFindByIdReturnsCountermeasureAndLogs(): void
    {
        $countermeasure = $this->createCountermeasure(['id' => 55]);

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(55)
            ->willReturn($countermeasure);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Countermeasure retrieved',
                $this->callback(static function (array $context) use ($countermeasure): bool {
                    return $context['id'] === $countermeasure->id
                        && $context['name'] === $countermeasure->name
                        && $context['targetType'] === $countermeasure->getTargetType()->value;
                })
            );

        $service = new CountermeasureService($repository, $logger);
        $this->assertSame($countermeasure, $service->findById(55));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(404)
            ->willReturn(null);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $service = new CountermeasureService($repository, $logger);
        $this->assertNull($service->findById(404));
    }

    public function testFindForHotspotDelegatesToRepositoryAndLogs(): void
    {
        $filter = new CountermeasureHotspotFilterDTO(
            targetType: TargetType::ROAD_SEGMENT,
            affectedCollisionTypes: [CollisionType::REAR_END],
            affectedSeverities: [InjurySeverity::SERIOUS]
        );

        $expected = [
            $this->createCountermeasure(['id' => 1, 'cmf' => 0.75]),
            $this->createCountermeasure(['id' => 2, 'cmf' => 0.65]),
        ];

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findForHotspot')
            ->with($filter)
            ->willReturn($expected);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Countermeasures retrieved for hotspot applicability',
                $this->callback(static function (array $context) use ($filter): bool {
                    return $context['targetType'] === $filter->targetType->value
                        && $context['affectedCollisionTypes'] === array_map(
                            static fn (CollisionType $type) => $type->value,
                            $filter->affectedCollisionTypes
                        )
                        && $context['affectedSeverities'] === array_map(
                            static fn (InjurySeverity $severity) => $severity->value,
                            $filter->affectedSeverities
                        )
                        && $context['allowedStatuses'] === array_map(
                            static fn (LifecycleStatus $status) => $status->value,
                            $filter->allowedStatuses
                        )
                        && $context['count'] === 2;
                })
            );

        $service = new CountermeasureService($repository, $logger);
        $this->assertSame($expected, $service->findForHotspot($filter));
    }

    public function testUpdateThrowsWhenCountermeasureMissing(): void
    {
        $countermeasure = $this->createCountermeasure(['id' => 77]);

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(77)
            ->willReturn(null);

        $service = new CountermeasureService($repository);

        $this->expectException(\InvalidArgumentException::class);
        $service->update($countermeasure);
    }

    public function testUpdatePersistsChangesAndLogs(): void
    {
        $countermeasure = $this->createCountermeasure([
            'id' => 88,
            'name' => 'Updated countermeasure',
            'lifecycle_status' => LifecycleStatus::IMPLEMENTED->value,
        ]);

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(88)
            ->willReturn($countermeasure);
        $repository->expects($this->once())
            ->method('update')
            ->with($countermeasure);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Countermeasure updated',
                $this->callback(static function (array $context) use ($countermeasure): bool {
                    return $context['id'] === $countermeasure->id
                        && $context['name'] === $countermeasure->name
                        && $context['targetType'] === $countermeasure->getTargetType()->value
                        && $context['lifecycleStatus'] === $countermeasure->lifecycleStatus->value;
                })
            );

        $service = new CountermeasureService($repository, $logger);
        $service->update($countermeasure);
    }

    public function testDeleteThrowsWhenCountermeasureMissing(): void
    {
        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(66)
            ->willReturn(null);

        $service = new CountermeasureService($repository);

        $this->expectException(\InvalidArgumentException::class);
        $service->delete(66);
    }

    public function testDeleteRemovesCountermeasureAndLogs(): void
    {
        $countermeasure = $this->createCountermeasure(['id' => 66]);

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(66)
            ->willReturn($countermeasure);
        $repository->expects($this->once())
            ->method('delete')
            ->with(66);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Countermeasure deleted',
                $this->callback(static function (array $context) use ($countermeasure): bool {
                    return $context['id'] === $countermeasure->id
                        && $context['name'] === $countermeasure->name
                        && $context['targetType'] === $countermeasure->getTargetType()->value;
                })
            );

        $service = new CountermeasureService($repository, $logger);
        $service->delete(66);
    }

    public function testAllReturnsEveryCountermeasure(): void
    {
        $countermeasures = [
            $this->createCountermeasure(['id' => 1]),
            $this->createCountermeasure(['id' => 2]),
        ];

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('all')
            ->willReturn($countermeasures);

        $service = new CountermeasureService($repository);
        $this->assertSame($countermeasures, $service->all());
    }

    public function testRecalculateCmfTriggeredByProjectEvaluation(): void
    {
        $project = new Project(
            id: 300,
            countermeasureId: 42,
            hotspotId: 21,
            period: new TimePeriod(
                new \DateTimeImmutable('2025-03-01'),
                new \DateTimeImmutable('2025-09-30')
            ),
            expectedCost: new MonetaryAmount(95000.0),
            actualCost: new MonetaryAmount(91000.0),
            status: ProjectStatus::IMPLEMENTED
        );

        $accident = AccidentFactory::create([
            'id' => 808,
            'occurredAt' => '2025-04-15 14:45:00',
            'type' => AccidentType::INJURY->value,
            'severity' => InjurySeverity::SEVERE->value,
        ]);

        /** @var CountermeasureRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(CountermeasureRepositoryInterface::class);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Countermeasure CMF recalculated after project evaluation',
                $this->callback(static function (array $context) use ($project, $accident): bool {
                    return $context['projectId'] === $project->id
                        && $context['countermeasureId'] === $project->countermeasureId
                        && $context['accidentId'] === $accident->id;
                })
            );

        $eventBus = new InMemoryEventBus();

        $service = new CountermeasureService($repository, $logger, $eventBus);
        $this->assertInstanceOf(CountermeasureService::class, $service);

        $eventBus->dispatch(new ProjectEvaluatedEvent($project, $accident));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCountermeasure(array $overrides = []): Countermeasure
    {
        $defaults = [
            'id' => 999,
            'name' => 'Enhanced road lighting',
            'target_type' => TargetType::ROAD_SEGMENT->value,
            'affected_collision_types' => [CollisionType::REAR_END->value, CollisionType::SIDESWIPE->value],
            'affected_severities' => [InjurySeverity::MINOR->value, InjurySeverity::SERIOUS->value],
            'cmf' => 0.8,
            'lifecycle_status' => LifecycleStatus::APPROVED->value,
            'implementation_cost' => ['amount' => 250000, 'currency' => 'USD'],
            'expected_annual_savings' => 50000,
            'evidence' => 'FHWA safety study',
            'applicability_rules' => [],
        ];

        return CountermeasureFactory::createFromArray(array_replace($defaults, $overrides));
    }
}

