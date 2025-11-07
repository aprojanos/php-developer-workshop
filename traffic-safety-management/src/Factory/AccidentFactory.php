<?php
namespace App\Factory;

use App\Model\AccidentInjury;
use App\Model\AccidentPDO;
use App\Model\AccidentBase;
use App\Enum\AccidentType;
use App\Enum\InjurySeverity;
use App\DTO\AccidentLocationDTO;
use App\Enum\LocationType;

final class AccidentFactory
{
    public static function create(array $data): AccidentBase
    {
        $id = $data['id'] ?? random_int(1000, 9999);
        $type = !empty($data['type']) ? AccidentType::from($data['type']) : AccidentType::PDO;
        
        // Handle location: if AccidentLocationDTO is provided, use it; otherwise create from roadSegmentId/intersectionId
        $location = $data['location'] ?? null;
        if (!$location instanceof AccidentLocationDTO) {
            $location = self::createLocationFromData($data);
        }
        
        $injuredPersonsCount = isset($data['injuredPersonsCount']) ? (int)$data['injuredPersonsCount'] : 0;

        return match ($type) {
            AccidentType::INJURY => new AccidentInjury(
                id: $id,
                occurredAt: new \DateTimeImmutable($data['occurredAt'] ?? 'now'),
                location: $location,
                cost: (float)($data['cost'] ?? 0),
                severity: !empty($data['severity']) ? InjurySeverity::from($data['severity']) : InjurySeverity::MINOR,
                injuredPersonsCount: max(0, $injuredPersonsCount),
            ),
            AccidentType::PDO => new AccidentPDO(
                id: $id,
                occurredAt: new \DateTimeImmutable($data['occurredAt'] ?? 'now'),
                location: $location,
                cost: (float)($data['cost'] ?? 0),
                severity: null,
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
}
