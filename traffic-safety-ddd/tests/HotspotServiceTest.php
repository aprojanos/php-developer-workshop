<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Service\HotspotService;
use SharedKernel\Contract\HotspotRepositoryInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\AccidentRepositoryInterface;
use SharedKernel\Contract\CostCalculatorStrategyInterface;
use App\Service\AccidentService;
use SharedKernel\DTO\AccidentSearchCriteria;
use SharedKernel\Enum\HotspotStatus;
use SharedKernel\Enum\LocationType;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\FunctionalClass;
use SharedKernel\DTO\AccidentLocationDTO;
use SharedKernel\DTO\HotspotScreeningDTO;
use SharedKernel\DTO\HotspotSearchDTO;
use SharedKernel\ValueObject\TimePeriod;
use SharedKernel\ValueObject\GeoLocation;
use SharedKernel\ValueObject\ObservedCrashes;
use SharedKernel\Model\Hotspot;
use SharedKernel\Model\RoadSegment;
use SharedKernel\Model\AccidentBase;
use App\Factory\AccidentFactory;
use SharedKernel\Domain\Event\HotspotCreatedEvent;
use SharedKernel\Domain\Event\InMemoryEventBus;

final class HotspotServiceTest extends TestCase
{
    public function testCreatePersistsHotspotAndLogs(): void
    {
        $hotspot = $this->createHotspot(['id' => 11]);

        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($hotspot);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Hotspot created',
                $this->callback(static function (array $context) use ($hotspot): bool {
                    return $context['id'] === $hotspot->id
                        && $context['status'] === $hotspot->status->value
                        && $context['riskScore'] === $hotspot->riskScore;
                })
            );

        $eventBus = new InMemoryEventBus();

        $service = new HotspotService($repository, $this->createAccidentService(), $logger, $eventBus);
        $service->create($hotspot);

        $this->assertCount(1, $eventBus->dispatchedEvents);
        $event = $eventBus->dispatchedEvents[0];
        $this->assertInstanceOf(HotspotCreatedEvent::class, $event);
        /** @var HotspotCreatedEvent $event */
        $this->assertSame($hotspot, $event->getHotspot());
    }

    public function testFindByIdReturnsHotspotAndLogs(): void
    {
        $hotspot = $this->createHotspot(['id' => 7, 'status' => HotspotStatus::REVIEWED]);

        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(7)
            ->willReturn($hotspot);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Hotspot retrieved',
                $this->callback(static function (array $context) use ($hotspot): bool {
                    return $context['id'] === $hotspot->id
                        && $context['status'] === $hotspot->status->value;
                })
            );

        $service = new HotspotService($repository, $this->createAccidentService(), $logger);
        $this->assertSame($hotspot, $service->findById(7));
    }

    public function testUpdateThrowsWhenHotspotMissing(): void
    {
        $hotspot = $this->createHotspot(['id' => 404]);

        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(404)
            ->willReturn(null);

        $service = new HotspotService($repository, $this->createAccidentService());

        $this->expectException(\InvalidArgumentException::class);
        $service->update($hotspot);
    }

    public function testUpdatePersistsChangesAndLogs(): void
    {
        $hotspot = $this->createHotspot(['id' => 21, 'riskScore' => 999.9, 'status' => HotspotStatus::ADDRESSED]);

        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(21)
            ->willReturn($hotspot);
        $repository->expects($this->once())
            ->method('update')
            ->with($hotspot);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Hotspot updated',
                $this->callback(static function (array $context) use ($hotspot): bool {
                    return $context['id'] === $hotspot->id
                        && $context['status'] === $hotspot->status->value
                        && $context['riskScore'] === $hotspot->riskScore;
                })
            );

        $service = new HotspotService($repository, $this->createAccidentService(), $logger);
        $service->update($hotspot);
    }

    public function testDeleteThrowsWhenHotspotMissing(): void
    {
        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(55)
            ->willReturn(null);

        $service = new HotspotService($repository, $this->createAccidentService());

        $this->expectException(\InvalidArgumentException::class);
        $service->delete(55);
    }

    public function testDeleteRemovesHotspotAndLogs(): void
    {
        $hotspot = $this->createHotspot(['id' => 33, 'status' => HotspotStatus::REVIEWED]);

        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(33)
            ->willReturn($hotspot);
        $repository->expects($this->once())
            ->method('delete')
            ->with(33);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Hotspot deleted',
                $this->callback(static fn (array $context): bool => $context['id'] === 33 && $context['status'] === HotspotStatus::REVIEWED->value)
            );

        $service = new HotspotService($repository, $this->createAccidentService(), $logger);
        $service->delete(33);
    }

    public function testSearchConvertsStatusAndSortsDescending(): void
    {
        $period = new TimePeriod(
            new \DateTimeImmutable('2025-02-01'),
            new \DateTimeImmutable('2025-02-28')
        );
        $dto = new HotspotSearchDTO(
            period: $period,
            roadSegmentId: 10,
            status: HotspotStatus::REVIEWED->value,
            minRiskScore: 100.0,
            maxRiskScore: 500.0
        );

        $hotspotHigh = $this->createHotspot(['id' => 1, 'riskScore' => 400.0]);
        $hotspotLow = $this->createHotspot(['id' => 2, 'riskScore' => 200.0]);

        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('search')
            ->with(
                $period,
                10,
                null,
                HotspotStatus::REVIEWED,
                100.0,
                500.0,
                null,
                null
            )
            ->willReturn([$hotspotLow, $hotspotHigh]); // deliberately unsorted

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Hotspot search performed',
                $this->callback(static function (array $context) use ($dto, $period): bool {
                    return $context['criteria']['roadSegmentId'] === $dto->roadSegmentId
                        && $context['criteria']['status'] === $dto->status
                        && $context['criteria']['minRiskScore'] === $dto->minRiskScore
                        && $context['criteria']['maxRiskScore'] === $dto->maxRiskScore
                        && $context['resultsCount'] === 2
                        && str_contains($context['criteria']['period'], $period->startDate->format('c'));
                })
            );

        $service = new HotspotService($repository, $this->createAccidentService(), $logger);
        $result = $service->search($dto);

        $this->assertSame([$hotspotHigh, $hotspotLow], $result);
    }

    public function testScreeningForHotspotsFiltersAccidentsAndExcludesExisting(): void
    {
        $roadSegmentAccidentA = $this->createAccident([
            'id' => 1,
            'roadSegmentId' => 100,
            'distanceFromStart' => 1.5,
            'cost' => 5000,
            'type' => AccidentType::INJURY->value,
            'severity' => 'serious',
            'occurredAt' => '2025-03-10',
        ]);
        $roadSegmentAccidentB = $this->createAccident([
            'id' => 2,
            'roadSegmentId' => 100,
            'distanceFromStart' => 2.0,
            'cost' => 6000,
            'type' => AccidentType::PDO->value,
            'occurredAt' => '2025-03-15',
        ]);
        $otherRoadAccident = $this->createAccident([
            'id' => 3,
            'roadSegmentId' => 200,
            'distanceFromStart' => 0.5,
            'cost' => 7000,
            'type' => AccidentType::INJURY->value,
            'severity' => 'minor',
            'occurredAt' => '2025-03-05',
        ]);
        $outsidePeriodAccident = $this->createAccident([
            'id' => 4,
            'roadSegmentId' => 100,
            'distanceFromStart' => 3.0,
            'cost' => 8000,
            'occurredAt' => '2025-04-01',
        ]);
        $intersectionAccident = $this->createAccident([
            'id' => 5,
            'type' => AccidentType::INJURY->value,
            'severity' => 'minor',
            'intersectionId' => 50,
            'occurredAt' => '2025-03-12',
        ]);

        $existingHotspot = $this->createHotspot([
            'id' => 999,
            'location' => $this->createRoadSegment(200),
        ]);

        /** @var HotspotRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(HotspotRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('all')
            ->willReturn([$existingHotspot]);
        $repository->expects($this->never())->method('save');

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Hotspot detection performed',
                $this->callback(static function (array $context): bool {
                    return $context['threshold'] === 10000.0
                        && $context['type'] === LocationType::ROADSEGMENT->value
                        && $context['totalAccidentsAnalyzed'] === 3
                        && $context['locationsAnalyzed'] === 2
                        && $context['hotspotsDetected'] === 1;
                })
            );

        $dto = new HotspotScreeningDTO(
            locationType: LocationType::ROADSEGMENT,
            threshold: 10000.0,
            period: new TimePeriod(
                new \DateTimeImmutable('2025-03-01'),
                new \DateTimeImmutable('2025-03-31')
            )
        );

        $service = new HotspotService(
            $repository,
            $this->createAccidentService([
                $roadSegmentAccidentA,
                $roadSegmentAccidentB,
                $otherRoadAccident,
                $outsidePeriodAccident,
                $intersectionAccident,
            ]),
            $logger
        );
        $result = $service->screeningForHotspots($dto);

        $this->assertSame([[
            'locationId' => 100,
            'score' => 11000.0,
            'accidentCount' => 2,
        ]], $result);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createHotspot(array $overrides = []): Hotspot
    {
        $defaults = [
            'id' => 1,
            'location' => $this->createRoadSegment(1),
            'period' => new TimePeriod(
                new \DateTimeImmutable('2025-01-01'),
                new \DateTimeImmutable('2025-01-31')
            ),
            'observedCrashes' => new ObservedCrashes([AccidentType::PDO->value => 3]),
            'expectedCrashes' => 2.5,
            'riskScore' => 250.0,
            'status' => HotspotStatus::OPEN,
            'screeningParameters' => ['source' => 'test'],
        ];

        $data = array_replace($defaults, $overrides);

        return new Hotspot(
            id: $data['id'],
            location: $data['location'],
            period: $data['period'],
            observedCrashes: $data['observedCrashes'],
            expectedCrashes: $data['expectedCrashes'],
            riskScore: $data['riskScore'],
            status: $data['status'],
            screeningParameters: $data['screeningParameters']
        );
    }

    private function createRoadSegment(int $id): RoadSegment
    {
        return new RoadSegment(
            id: $id,
            code: 'RS-' . $id,
            lengthKm: 2.5,
            laneCount: 2,
            functionalClass: FunctionalClass::URBAN,
            speedLimitKmh: 50,
            aadt: 12000,
            geoLocation: new GeoLocation('POINT(0 0)')
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAccident(array $overrides = []): AccidentBase
    {
        $defaults = [
            'id' => 500,
            'occurredAt' => '2025-03-10',
            'type' => AccidentType::PDO->value,
            'severity' => null,
            'cost' => 1000.0,
            'roadSegmentId' => null,
            'distanceFromStart' => null,
            'latitude' => 47.5,
            'longitude' => 19.0,
            'injuredPersonsCount' => 0,
        ];

        $data = array_replace($defaults, $overrides);

        if (isset($data['roadSegmentId']) !== true && isset($data['intersectionId']) !== true) {
            $data['roadSegmentId'] = 1;
            $data['distanceFromStart'] = 1.0;
        }

        return AccidentFactory::create($data);
    }

    /**
     * @param AccidentBase[] $accidents
     */
    private function createAccidentService(array $accidents = []): AccidentService
    {
        $repository = new class($accidents) implements AccidentRepositoryInterface {
            /**
             * @param array<int, AccidentBase> $accidents
             */
            public function __construct(private array $accidents) {}

            public function save(AccidentBase $accident): void
            {
                $this->accidents[$accident->id] = $accident;
            }

            public function all(): array
            {
                return array_values($this->accidents);
            }

            public function findById(int $id): ?AccidentBase
            {
                return $this->accidents[$id] ?? null;
            }

            public function update(AccidentBase $accident): void
            {
                $this->accidents[$accident->id] = $accident;
            }

            public function delete(int $id): void
            {
                unset($this->accidents[$id]);
            }

            public function findByLocation(AccidentLocationDTO $location): array
            {
                return array_values(array_filter(
                    $this->accidents,
                    static fn (AccidentBase $accident): bool => $accident->location->locationType === $location->locationType
                        && $accident->location->locationId === $location->locationId
                ));
            }

            public function search(AccidentSearchCriteria $criteria): array
            {
                return [];
            }
        };

        $calculator = new class implements CostCalculatorStrategyInterface {
            public function calculate(AccidentBase $accident): float
            {
                return $accident->cost;
            }
        };

        return new AccidentService($repository, $calculator);
    }
}


