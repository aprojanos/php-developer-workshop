<?php
namespace AccidentContext\Application;

use SharedKernel\Contract\AccidentRepositoryInterface;
use SharedKernel\Contract\CostCalculatorStrategyInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\NotifierInterface;
use SharedKernel\Domain\Event\AccidentCreatedEvent;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Model\AccidentBase;
use Google\Protobuf\Timestamp;
use Traffic\Grpc\Accident\V1\Accident as AccidentMessage;
use Traffic\Grpc\Accident\V1\AccidentLocation as AccidentLocationMessage;
use Traffic\Grpc\Accident\V1\AllAccidentsResponse;
use Traffic\Grpc\Accident\V1\LocationType as ProtoLocationType;
use SharedKernel\DTO\AccidentLocationDTO;
use AccidentContext\Domain\Service\SimpleCostCalculator;
use SharedKernel\DTO\AccidentSearchCriteria;
use SharedKernel\DTO\AccidentSearchDTO;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;

final class AccidentService
{
    public function __construct(
        private AccidentRepositoryInterface $repository,
        private CostCalculatorStrategyInterface $costCalculator = new SimpleCostCalculator(),
        private ?LoggerInterface $logger = null,
        private ?NotifierInterface $notifier = null,
        private ?EventBusInterface $eventBus = null,
    ) {}

    public function create(AccidentBase $accident): void
    {
        $accident->recordEvent(new AccidentCreatedEvent($accident));

        $this->repository->save($accident);

        // log
        $this->logger?->info('Accident created', [
            'id' => $accident->id,
            'type' => $accident->getType()->value,
            'cost' => $accident->cost
        ]);

        // notify external system / email
        $this->notifier?->notify([
            'id' => $accident->id,
            'occurredAt' => $accident->occurredAt->format('c'),
            'location' => $accident->location,
            'type' => $accident->getType()->value,
            'cost' => $accident->cost,
        ]);

        foreach ($accident->releaseEvents() as $event) {
            $this->eventBus?->dispatch($event);
        }
    }

    public function totalEstimatedCost(): float
    {
        $sum = 0.0;
        foreach ($this->repository->all() as $accident) {
            $sum += $this->costCalculator->calculate($accident);
        }
        return $sum;
    }

    /**
     * Get all accidents
     *
     * @return AccidentBase[] Array of all accidents
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * Calculate total estimated cost for an array of accidents
     *
     * @param AccidentBase[] $accidents Array of accidents
     * @return float Total estimated cost
     */
    public function calculateTotalCost(array $accidents): float
    {
        $sum = 0.0;
        foreach ($accidents as $accident) {
            $sum += $this->costCalculator->calculate($accident);
        }
        return $sum;
    }

    /**
     * Find an accident by its ID
     *
     * @param int $id The accident ID
     * @return AccidentBase|null The accident or null if not found
     */
    public function findById(int $id): ?AccidentBase
    {
        $accident = $this->repository->findById($id);

        if ($accident !== null) {
            $this->logger?->info('Accident retrieved', [
                'id' => $accident->id,
                'type' => $accident->getType()->value,
            ]);
        }

        return $accident;
    }

    /**
     * Update an existing accident
     *
     * @param AccidentBase $accident The accident to update
     * @return void
     */
    public function update(AccidentBase $accident): void
    {
        // Check if accident exists
        $existing = $this->repository->findById($accident->id);
        if ($existing === null) {
            throw new \InvalidArgumentException("Accident with ID {$accident->id} not found");
        }

        $this->repository->update($accident);

        // log
        $this->logger?->info('Accident updated', [
            'id' => $accident->id,
            'type' => $accident->getType()->value,
            'cost' => $accident->cost
        ]);

        // notify external system / email
        $this->notifier?->notify([
            'id' => $accident->id,
            'action' => 'updated',
            'occurredAt' => $accident->occurredAt->format('c'),
            'location' => $accident->location,
            'type' => $accident->getType()->value,
            'cost' => $accident->cost,
        ]);
    }

    /**
     * Delete an accident by its ID
     *
     * @param int $id The accident ID
     * @return void
     */
    public function delete(int $id): void
    {
        // Check if accident exists
        $accident = $this->repository->findById($id);
        if ($accident === null) {
            throw new \InvalidArgumentException("Accident with ID {$id} not found");
        }

        $this->repository->delete($id);

        // log
        $this->logger?->info('Accident deleted', [
            'id' => $id,
            'type' => $accident->getType()->value,
        ]);

        // notify external system / email
        $this->notifier?->notify([
            'id' => $id,
            'action' => 'deleted',
            'type' => $accident->getType()->value,
        ]);
    }

    /**
     * Search for accidents based on multiple criteria
     *
     * @param AccidentSearchDTO $searchDTO Search criteria DTO
     * @return AccidentBase[] Array of matching accidents
     */
    public function search(AccidentSearchDTO $searchDTO): array
    {
        // Convert enum strings to enum objects
        $convertedSeverity = $this->convertEnum($searchDTO->severity, InjurySeverity::class);
        $convertedType = $this->convertEnum($searchDTO->type, AccidentType::class);
        $convertedCollisionType = $this->convertEnum($searchDTO->collisionType, CollisionType::class);
        $convertedCauseFactor = $this->convertEnum($searchDTO->causeFactor, CauseFactor::class);
        $convertedWeatherCondition = $this->convertEnum($searchDTO->weatherCondition, WeatherCondition::class);
        $convertedRoadCondition = $this->convertEnum($searchDTO->roadCondition, RoadCondition::class);
        $convertedVisibilityCondition = $this->convertEnum($searchDTO->visibilityCondition, VisibilityCondition::class);

        $criteria = new AccidentSearchCriteria(
            occurredAtInterval: $searchDTO->occurredAtInterval,
            location: $searchDTO->location,
            severity: $convertedSeverity,
            type: $convertedType,
            collisionType: $convertedCollisionType,
            causeFactor: $convertedCauseFactor,
            weatherCondition: $convertedWeatherCondition,
            roadCondition: $convertedRoadCondition,
            visibilityCondition: $convertedVisibilityCondition,
            injuredPersonsCount: $searchDTO->injuredPersonsCount
        );

        $accidents = $this->repository->search($criteria);

        $this->logger?->info('Accident search performed', [
            'criteria' => [
                'occurredAtInterval' => $searchDTO->occurredAtInterval
                    ? $searchDTO->occurredAtInterval->startDate->format('c') . ' to ' . $searchDTO->occurredAtInterval->endDate->format('c')
                    : null,
                'location' => $searchDTO->location?->locationType->value . ':' . $searchDTO->location?->locationId,
                'severity' => $searchDTO->severity,
                'type' => $searchDTO->type,
                'collisionType' => $searchDTO->collisionType,
                'causeFactor' => $searchDTO->causeFactor,
                'weatherCondition' => $searchDTO->weatherCondition,
                'roadCondition' => $searchDTO->roadCondition,
                'visibilityCondition' => $searchDTO->visibilityCondition,
                'injuredPersonsCount' => $searchDTO->injuredPersonsCount,
            ],
            'resultsCount' => count($accidents),
        ]);

        return $accidents;
    }

    /**
     * Export accidents in gRPC format.
     */
    public function allAsGrpcResponse(): AllAccidentsResponse
    {
        $response = new AllAccidentsResponse();
        $messages = [];

        foreach ($this->all() as $accident) {
            $messages[] = $this->mapAccidentToMessage($accident);
        }

        $response->setAccidents($messages);

        return $response;
    }

    private function mapAccidentToMessage(AccidentBase $accident): AccidentMessage
    {
        $message = new AccidentMessage();
        $message->setId($accident->id);
        $message->setOccurredAt($this->createTimestamp($accident->occurredAt));
        $message->setLocation($this->mapLocationToMessage($accident->location));
        $message->setCost($accident->cost);
        $message->setAccidentType($accident->getType()->value);
        $message->setSeverity($accident->severity?->value ?? '');
        $message->setCollisionType($accident->collisionType?->value ?? '');
        $message->setCauseFactor($accident->causeFactor?->value ?? '');
        $message->setWeatherCondition($accident->weatherCondition?->value ?? '');
        $message->setRoadCondition($accident->roadCondition?->value ?? '');
        $message->setVisibilityCondition($accident->visibilityCondition?->value ?? '');
        $message->setInjuredPersonsCount($accident->injuredPersonsCount);

        return $message;
    }

    private function mapLocationToMessage(AccidentLocationDTO $location): AccidentLocationMessage
    {
        $message = new AccidentLocationMessage();
        $message->setType(match ($location->locationType) {
            \SharedKernel\Enum\LocationType::ROADSEGMENT => ProtoLocationType::LOCATION_TYPE_ROADSEGMENT,
            \SharedKernel\Enum\LocationType::INTERSECTION => ProtoLocationType::LOCATION_TYPE_INTERSECTION,
        });
        $message->setLocationId($location->locationId);
        $message->setLatitude($location->latitude);
        $message->setLongitude($location->longitude);

        if ($location->distanceFromStart !== null) {
            $message->setDistanceFromStart($location->distanceFromStart);
        }

        return $message;
    }

    private function createTimestamp(\DateTimeImmutable $dateTime): Timestamp
    {
        $timestamp = new Timestamp();
        $timestamp->setSeconds($dateTime->getTimestamp());
        $timestamp->setNanos((int) $dateTime->format('u') * 1000);

        return $timestamp;
    }

    /**
     * Convert a string or enum to an enum instance
     *
     * @param \BackedEnum|string|null $value The value to convert
     * @param class-string<\BackedEnum> $enumClass The enum class name
     * @return \BackedEnum|null The enum instance or null
     */
    private function convertEnum(mixed $value, string $enumClass): ?\BackedEnum
    {
        if ($value === null) {
            return null;
        }

        // Check if value is already the correct enum instance
        if (is_object($value) && $value::class === $enumClass) {
            return $value;
        }

        // Convert string to enum
        return is_string($value) && is_subclass_of($enumClass, \BackedEnum::class)
            ? $enumClass::tryFrom($value)
            : null;
    }
}
