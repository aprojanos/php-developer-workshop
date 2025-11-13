<?php
namespace AccidentContext\Domain\Factory;

use SharedKernel\Model\AccidentInjury;
use SharedKernel\Model\AccidentPDO;
use SharedKernel\Model\AccidentBase;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\DTO\AccidentLocationDTO;
use SharedKernel\Enum\LocationType;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;

final class AccidentFactory
{
    public static function create(array $data): AccidentBase
    {
        $id = $data['id'] ?? random_int(10000, 99999);
        $type = !empty($data['type']) ? AccidentType::from($data['type']) : AccidentType::PDO;

        // Handle location: if AccidentLocationDTO is provided, use it; otherwise create from roadSegmentId/intersectionId
        $location = $data['location'] ?? null;
        if (!$location instanceof AccidentLocationDTO) {
            $location = self::createLocationFromData($data);
        }
        $injuredPersonsCount = isset($data['injuredPersonsCount']) ? (int)$data['injuredPersonsCount'] : 0;
        $occurredAt = $data['occurredAt'] ?? 'now';
        $occurredAt = $occurredAt instanceof \DateTimeImmutable
            ? $occurredAt
            : new \DateTimeImmutable((string)$occurredAt);

        $severity = !empty($data['severity']) ? InjurySeverity::from($data['severity']) : null;
        $collisionType = self::parseEnum($data['collisionType'] ?? null, CollisionType::class);
        $causeFactor = self::parseEnum($data['causeFactor'] ?? null, CauseFactor::class);
        $weatherCondition = self::parseEnum($data['weatherCondition'] ?? null, WeatherCondition::class);
        $roadCondition = self::parseEnum($data['roadCondition'] ?? null, RoadCondition::class);
        $visibilityCondition = self::parseEnum($data['visibilityCondition'] ?? null, VisibilityCondition::class);
        $locationDescription = $data['locationDescription'] ?? null;

        return match ($type) {
            AccidentType::INJURY => new AccidentInjury(
                id: $id,
                occurredAt: $occurredAt,
                location: $location,
                cost: (float)($data['cost'] ?? 0),
                severity: $severity ?? InjurySeverity::MINOR,
                locationDescription: $locationDescription,
                collisionType: $collisionType,
                causeFactor: $causeFactor,
                weatherCondition: $weatherCondition,
                roadCondition: $roadCondition,
                visibilityCondition: $visibilityCondition,
                injuredPersonsCount: max(0, $injuredPersonsCount),
            ),
            AccidentType::PDO => new AccidentPDO(
                id: $id,
                occurredAt: $occurredAt,
                location: $location,
                cost: (float)($data['cost'] ?? 0),
                severity: null,
                locationDescription: $locationDescription,
                collisionType: $collisionType,
                causeFactor: $causeFactor,
                weatherCondition: $weatherCondition,
                roadCondition: $roadCondition,
                visibilityCondition: $visibilityCondition,
                injuredPersonsCount: 0,
            ),
        };
    }

    private static function createLocationFromData(array $data): AccidentLocationDTO
    {
        $roadSegmentId = $data['roadSegmentId'] ?? null;
        $intersectionId = $data['intersectionId'] ?? null;
        
        // Determine location type and ID
        if ($roadSegmentId !== null) {
            $locationType = LocationType::ROADSEGMENT;
            $locationId = (int)$roadSegmentId;
            $distanceFromStart = $data['distanceFromStart'] ?? 0.0;
        } elseif ($intersectionId !== null) {
            $locationType = LocationType::INTERSECTION;
            $locationId = (int)$intersectionId;
            $distanceFromStart = null;
        } else {
            // Default to roadsegment with ID 0 if neither is provided
            $locationType = LocationType::ROADSEGMENT;
            $locationId = 0;
            $distanceFromStart = 0.0;
        }

        return new AccidentLocationDTO(
            locationType: $locationType,
            locationId: $locationId,
            latitude: (float)($data['latitude'] ?? 0.0),
            longitude: (float)($data['longitude'] ?? 0.0),
            distanceFromStart: $distanceFromStart
        );
    }

    private static function parseEnum(mixed $value, string $enumClass): ?\BackedEnum
    {
        $enum = null;

        if ($value instanceof \BackedEnum && $value instanceof $enumClass) {
            $enum = $value;
        } elseif (
            is_string($value)
            && is_subclass_of($enumClass, \BackedEnum::class)
        ) {
            $enum = $enumClass::tryFrom($value);
        }

        return $enum;
    }
}
