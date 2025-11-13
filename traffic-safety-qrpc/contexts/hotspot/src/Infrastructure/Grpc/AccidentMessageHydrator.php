<?php

namespace HotspotContext\Infrastructure\Grpc;

use Google\Protobuf\Timestamp;
use SharedKernel\DTO\AccidentLocationDTO;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\LocationType;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\Model\AccidentBase;
use SharedKernel\Model\AccidentInjury;
use SharedKernel\Model\AccidentPDO;
use Traffic\Grpc\Accident\V1\Accident;
use Traffic\Grpc\Accident\V1\AccidentLocation;
use Traffic\Grpc\Accident\V1\LocationType as ProtoLocationType;

final class AccidentMessageHydrator
{
    public function fromMessage(Accident $message): AccidentBase
    {
        $location = $this->mapLocation($message->getLocation());

        $occurredAt = $this->mapTimestamp($message->getOccurredAt());

        $type = AccidentType::tryFrom($message->getAccidentType());

        $severity = InjurySeverity::tryFrom($message->getSeverity());
        $collisionType = CollisionType::tryFrom($message->getCollisionType());
        $causeFactor = CauseFactor::tryFrom($message->getCauseFactor());
        $weather = WeatherCondition::tryFrom($message->getWeatherCondition());
        $road = RoadCondition::tryFrom($message->getRoadCondition());
        $visibility = VisibilityCondition::tryFrom($message->getVisibilityCondition());

        return match ($type) {
            AccidentType::INJURY => new AccidentInjury(
                id: $message->getId(),
                occurredAt: $occurredAt,
                location: $location,
                cost: $message->getCost(),
                severity: $severity,
                collisionType: $collisionType,
                causeFactor: $causeFactor,
                weatherCondition: $weather,
                roadCondition: $road,
                visibilityCondition: $visibility,
                injuredPersonsCount: $message->getInjuredPersonsCount()
            ),
            AccidentType::PDO, null => new AccidentPDO(
                id: $message->getId(),
                occurredAt: $occurredAt,
                location: $location,
                cost: $message->getCost(),
                severity: $severity,
                collisionType: $collisionType,
                causeFactor: $causeFactor,
                weatherCondition: $weather,
                roadCondition: $road,
                visibilityCondition: $visibility,
                injuredPersonsCount: $message->getInjuredPersonsCount()
            ),
            default => throw new \UnexpectedValueException(
                sprintf('Unsupported accident type "%s" received from gRPC service', $message->getAccidentType())
            ),
        };
    }

    private function mapLocation(AccidentLocation $location): AccidentLocationDTO
    {
        $type = match ($location->getType()) {
            ProtoLocationType::LOCATION_TYPE_ROADSEGMENT => LocationType::ROADSEGMENT,
            ProtoLocationType::LOCATION_TYPE_INTERSECTION => LocationType::INTERSECTION,
            default => LocationType::ROADSEGMENT,
        };

        $distance = $location->hasDistanceFromStart()
            ? $location->getDistanceFromStart()
            : null;

        return new AccidentLocationDTO(
            locationType: $type,
            locationId: $location->getLocationId(),
            latitude: $location->getLatitude(),
            longitude: $location->getLongitude(),
            distanceFromStart: $distance
        );
    }

    private function mapTimestamp(Timestamp $timestamp): \DateTimeImmutable
    {
        $seconds = $timestamp->getSeconds();
        $nanos = $timestamp->getNanos();

        $microseconds = (int) floor($nanos / 1000);
        $formatted = sprintf('%.6F', $seconds + $microseconds / 1_000_000);

        $dateTime = \DateTimeImmutable::createFromFormat('U.u', $formatted, new \DateTimeZone('UTC'));
        if ($dateTime === false) {
            throw new \RuntimeException('Failed to convert gRPC timestamp to DateTimeImmutable');
        }

        return $dateTime;
    }
}

