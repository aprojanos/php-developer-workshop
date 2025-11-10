<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Service\AccidentService;
use App\Service\SimpleCostCalculator;
use App\Factory\AccidentFactory;
use SharedKernel\Contract\AccidentRepositoryInterface;
use SharedKernel\Contract\CostCalculatorStrategyInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\NotifierInterface;
use SharedKernel\DTO\AccidentSearchCriteria;
use SharedKernel\DTO\AccidentLocationDTO;
use SharedKernel\DTO\AccidentSearchDTO;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\LocationType;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\Model\AccidentBase;
use SharedKernel\ValueObject\TimePeriod;

final class AccidentServiceTest extends TestCase
{
    public function testCreatePersistsAccidentAndDispatchesNotifications(): void
    {
        $accident = $this->createAccident();

        /** @var AccidentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AccidentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($accident);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Accident created',
                $this->callback(static function (array $context) use ($accident): bool {
                    return $context['id'] === $accident->id
                        && $context['type'] === $accident->getType()->value
                        && $context['cost'] === $accident->cost;
                })
            );

        /** @var NotifierInterface&MockObject $notifier */
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(static function (array $payload) use ($accident): bool {
                return $payload['id'] === $accident->id
                    && $payload['type'] === $accident->getType()->value
                    && $payload['cost'] === $accident->cost
                    && $payload['location'] === $accident->location
                    && $payload['occurredAt'] === $accident->occurredAt->format('c');
            }));

        $service = new AccidentService($repository, new SimpleCostCalculator(), $logger, $notifier);
        $service->create($accident);
    }

    public function testTotalEstimatedCostSumsCalculatorOutput(): void
    {
        $accidents = [
            $this->createAccident(['id' => 1, 'severity' => 'minor', 'cost' => 100]),
            $this->createAccident(['id' => 2, 'severity' => 'fatal', 'cost' => 200]),
        ];

        /** @var AccidentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AccidentRepositoryInterface::class);
        $repository->method('all')->willReturn($accidents);

        /** @var CostCalculatorStrategyInterface&MockObject $calculator */
        $calculator = $this->createMock(CostCalculatorStrategyInterface::class);
        $calculator->expects($this->exactly(2))
            ->method('calculate')
            ->withConsecutive([$accidents[0]], [$accidents[1]])
            ->willReturnOnConsecutiveCalls(120.5, 450.0);

        $service = new AccidentService($repository, $calculator);

        $this->assertSame(570.5, $service->totalEstimatedCost());
    }

    public function testUpdateThrowsWhenAccidentMissing(): void
    {
        $accident = $this->createAccident(['id' => 404]);

        /** @var AccidentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AccidentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(404)
            ->willReturn(null);

        $service = new AccidentService($repository, new SimpleCostCalculator());

        $this->expectException(\InvalidArgumentException::class);
        $service->update($accident);
    }

    public function testUpdatePersistsChangesAndSendsNotifications(): void
    {
        $accident = $this->createAccident(['id' => 77, 'severity' => 'serious', 'cost' => 7500]);

        /** @var AccidentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AccidentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(77)
            ->willReturn($accident);
        $repository->expects($this->once())
            ->method('update')
            ->with($accident);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Accident updated',
                $this->callback(static function (array $context) use ($accident): bool {
                    return $context['id'] === $accident->id
                        && $context['type'] === $accident->getType()->value
                        && $context['cost'] === $accident->cost;
                })
            );

        /** @var NotifierInterface&MockObject $notifier */
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(static function (array $payload) use ($accident): bool {
                return $payload['id'] === $accident->id
                    && $payload['action'] === 'updated'
                    && $payload['type'] === $accident->getType()->value;
            }));

        $service = new AccidentService($repository, new SimpleCostCalculator(), $logger, $notifier);
        $service->update($accident);
    }

    public function testDeleteThrowsWhenAccidentMissing(): void
    {
        /** @var AccidentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AccidentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(55)
            ->willReturn(null);

        $service = new AccidentService($repository, new SimpleCostCalculator());

        $this->expectException(\InvalidArgumentException::class);
        $service->delete(55);
    }

    public function testDeleteRemovesAccidentAndNotifies(): void
    {
        $accident = $this->createAccident(['id' => 33]);

        /** @var AccidentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AccidentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(33)
            ->willReturn($accident);
        $repository->expects($this->once())
            ->method('delete')
            ->with(33);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Accident deleted',
                $this->callback(static fn (array $context): bool => $context['id'] === 33)
            );

        /** @var NotifierInterface&MockObject $notifier */
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(static fn (array $payload): bool => $payload['id'] === 33 && $payload['action'] === 'deleted'));

        $service = new AccidentService($repository, new SimpleCostCalculator(), $logger, $notifier);
        $service->delete(33);
    }

    public function testSearchConvertsStringCriteriaToEnums(): void
    {
        $period = new TimePeriod(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-01-31')
        );
        $location = new AccidentLocationDTO(
            LocationType::ROADSEGMENT,
            9,
            47.4979,
            19.0402,
            3.2
        );

        $dto = new AccidentSearchDTO(
            occurredAtInterval: $period,
            location: $location,
            severity: InjurySeverity::SERIOUS->value,
            type: AccidentType::INJURY->value,
            collisionType: CollisionType::HEAD_ON->value,
            causeFactor: CauseFactor::SPEEDING->value,
            weatherCondition: WeatherCondition::RAIN->value,
            roadCondition: RoadCondition::WET->value,
            visibilityCondition: VisibilityCondition::POOR->value,
            injuredPersonsCount: 3
        );

        $expectedResults = ['matching-accident'];

        /** @var AccidentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AccidentRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('search')
            ->with($this->callback(static function (AccidentSearchCriteria $criteria) use (
                $period,
                $location
            ): bool {
                return $criteria->occurredAtInterval === $period
                    && $criteria->location === $location
                    && $criteria->severity === InjurySeverity::SERIOUS
                    && $criteria->type === AccidentType::INJURY
                    && $criteria->collisionType === CollisionType::HEAD_ON
                    && $criteria->causeFactor === CauseFactor::SPEEDING
                    && $criteria->weatherCondition === WeatherCondition::RAIN
                    && $criteria->roadCondition === RoadCondition::WET
                    && $criteria->visibilityCondition === VisibilityCondition::POOR
                    && $criteria->injuredPersonsCount === 3;
            }))
            ->willReturn($expectedResults);

        $service = new AccidentService($repository, new SimpleCostCalculator());

        $this->assertSame($expectedResults, $service->search($dto));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAccident(array $overrides = []): AccidentBase
    {
        return AccidentFactory::create(array_replace([
            'id' => 101,
            'occurredAt' => '2025-01-15 08:30:00',
            'type' => AccidentType::INJURY->value,
            'severity' => InjurySeverity::MINOR->value,
            'cost' => 1250.5,
            'roadSegmentId' => 42,
            'distanceFromStart' => 2.5,
            'latitude' => 47.4979,
            'longitude' => 19.0402,
            'injuredPersonsCount' => 1,
        ], $overrides));
    }
}
