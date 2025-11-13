<?php

declare(strict_types=1);

namespace App\Accident;

use SharedKernel\Model\AccidentBase;

final class AccidentPayloadBuilder
{
    /**
     * Build a payload compatible with {@see \AccidentContext\Domain\Factory\AccidentFactory::create()}
     * from an existing {@see AccidentBase} instance.
     *
     * @return array<string, mixed>
     */
    public static function toFactoryPayload(AccidentBase $accident): array
    {
        $location = $accident->location;

        $payload = [
            'id' => $accident->id,
            'occurredAt' => $accident->occurredAt->format('c'),
            'type' => $accident->getType()->value,
            'severity' => $accident->severity?->value,
            'cost' => $accident->cost,
            'collisionType' => $accident->collisionType?->value,
            'causeFactor' => $accident->causeFactor?->value,
            'weatherCondition' => $accident->weatherCondition?->value,
            'roadCondition' => $accident->roadCondition?->value,
            'visibilityCondition' => $accident->visibilityCondition?->value,
            'injuredPersonsCount' => $accident->injuredPersonsCount,
            'locationDescription' => $accident->getLocationDescription(),
            'roadSegmentId' => $location->getRoadSegmentId(),
            'intersectionId' => $location->getIntersectionId(),
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'distanceFromStart' => $location->distanceFromStart,
        ];

        return array_filter(
            $payload,
            static fn(mixed $value): bool => $value !== null
        );
    }

    private function __construct()
    {
        // Static utility.
    }
}


