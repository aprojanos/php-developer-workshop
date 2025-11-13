<?php

declare(strict_types=1);

namespace App\GraphQL\Accident;

use SharedKernel\Enum\LocationType;

final class AccidentInputMapper
{
    /**
     * Normalize an accident input array so it can be consumed by the
     * {@see \AccidentContext\Domain\Factory\AccidentFactory}.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function toAccidentPayload(array $input): array
    {
        $payload = [];

        if (array_key_exists('id', $input)) {
            $payload['id'] = (int)$input['id'];
        }

        if (array_key_exists('occurredAt', $input)) {
            $payload['occurredAt'] = (string)$input['occurredAt'];
        }

        foreach (['type', 'severity', 'collisionType', 'causeFactor', 'weatherCondition', 'roadCondition', 'visibilityCondition'] as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                if ($value !== null) {
                    $payload[$key] = (string)$value;
                }
            }
        }

        if (array_key_exists('cost', $input)) {
            $payload['cost'] = (float)$input['cost'];
        }

        if (array_key_exists('injuredPersonsCount', $input)) {
            $payload['injuredPersonsCount'] = (int)$input['injuredPersonsCount'];
        }

        if (array_key_exists('locationDescription', $input)) {
            $payload['locationDescription'] = $input['locationDescription'];
        }

        if (isset($input['location']) && is_array($input['location'])) {
            $location = $input['location'];
            $locationType = isset($location['locationType'])
                ? LocationType::from((string)$location['locationType'])
                : LocationType::ROADSEGMENT;

            $payload['latitude'] = isset($location['latitude']) ? (float)$location['latitude'] : 0.0;
            $payload['longitude'] = isset($location['longitude']) ? (float)$location['longitude'] : 0.0;

            if ($locationType === LocationType::ROADSEGMENT) {
                $payload['roadSegmentId'] = (int)($location['locationId'] ?? 0);
                $payload['distanceFromStart'] = isset($location['distanceFromStart'])
                    ? (float)$location['distanceFromStart']
                    : null;
                $payload['intersectionId'] = null;
            } else {
                $payload['intersectionId'] = (int)($location['locationId'] ?? 0);
                $payload['roadSegmentId'] = null;
                $payload['distanceFromStart'] = null;
            }
        }

        return array_filter(
            $payload,
            static fn(mixed $value): bool => $value !== null
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function toSearchCriteria(array $input): array
    {
        $criteria = [];

        if (isset($input['occurredAtInterval']) && is_array($input['occurredAtInterval'])) {
            $criteria['occurredAtInterval'] = [
                'startDate' => (string)$input['occurredAtInterval']['startDate'],
                'endDate' => (string)$input['occurredAtInterval']['endDate'],
            ];
        }

        if (isset($input['location']) && is_array($input['location'])) {
            $location = $input['location'];
            $criteria['location'] = array_filter([
                'locationType' => (string)$location['locationType'],
                'locationId' => isset($location['locationId']) ? (int)$location['locationId'] : null,
                'latitude' => isset($location['latitude']) ? (float)$location['latitude'] : null,
                'longitude' => isset($location['longitude']) ? (float)$location['longitude'] : null,
                'distanceFromStart' => isset($location['distanceFromStart']) ? (float)$location['distanceFromStart'] : null,
            ], static fn(mixed $value): bool => $value !== null);
        }

        foreach (['severity', 'type', 'collisionType', 'causeFactor', 'weatherCondition', 'roadCondition', 'visibilityCondition'] as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                if ($value !== null && $value !== '') {
                    $criteria[$key] = (string)$value;
                }
            }
        }

        if (array_key_exists('injuredPersonsCount', $input)) {
            $criteria['injuredPersonsCount'] = (int)$input['injuredPersonsCount'];
        }

        return $criteria;
    }

    private function __construct()
    {
        // Static utility.
    }
}


