<?php

declare(strict_types=1);

namespace App\Http\Serializer;

use SharedKernel\DTO\AccidentLocationDTO;
use SharedKernel\Model\AccidentBase;
use SharedKernel\Model\Countermeasure;
use SharedKernel\Model\Hotspot;
use SharedKernel\Model\Intersection;
use SharedKernel\Model\Project;
use SharedKernel\Model\RoadSegment;
use SharedKernel\ValueObject\ObservedCrashes;
use SharedKernel\ValueObject\TimePeriod;
use SharedKernel\ValueObject\MonetaryAmount;

final class DomainSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function accident(AccidentBase $accident): array
    {
        return [
            'id' => $accident->id,
            'occurredAt' => $accident->occurredAt->format('c'),
            'location' => self::accidentLocation($accident->location),
            'cost' => $accident->cost,
            'type' => $accident->getType()->value,
            'severity' => $accident->severity?->value,
            'collisionType' => $accident->collisionType?->value,
            'causeFactor' => $accident->causeFactor?->value,
            'weatherCondition' => $accident->weatherCondition?->value,
            'roadCondition' => $accident->roadCondition?->value,
            'visibilityCondition' => $accident->visibilityCondition?->value,
            'injuredPersonsCount' => $accident->injuredPersonsCount,
            'locationDescription' => $accident->getLocationDescription(),
            'requiresImmediateAttention' => $accident->requiresImmediateAttention(),
            'daysSinceOccurrence' => $accident->getDaysSinceOccurrence(),
        ];
    }

    /**
     * @param AccidentBase[] $accidents
     * @return list<array<string, mixed>>
     */
    public static function accidents(array $accidents): array
    {
        return array_map([self::class, 'accident'], $accidents);
    }

    /**
     * @return array<string, mixed>
     */
    public static function accidentLocation(AccidentLocationDTO $location): array
    {
        return [
            'locationType' => $location->locationType->value,
            'locationId' => $location->locationId,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'distanceFromStart' => $location->distanceFromStart,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function project(Project $project): array
    {
        return [
            'id' => $project->id,
            'countermeasureId' => $project->countermeasureId,
            'hotspotId' => $project->hotspotId,
            'period' => self::timePeriod($project->period),
            'expectedCost' => self::monetaryAmount($project->expectedCost),
            'actualCost' => self::monetaryAmount($project->actualCost),
            'status' => $project->status->value,
        ];
    }

    /**
     * @param Project[] $projects
     * @return list<array<string, mixed>>
     */
    public static function projects(array $projects): array
    {
        return array_map([self::class, 'project'], $projects);
    }

    /**
     * @return array<string, mixed>
     */
    public static function countermeasure(Countermeasure $countermeasure): array
    {
        return [
            'id' => $countermeasure->id,
            'name' => $countermeasure->name,
            'targetType' => $countermeasure->getTargetType()->value,
            'affectedCollisionTypes' => array_map(static fn($type) => $type->value, $countermeasure->affectedCollisionTypes),
            'affectedSeverities' => array_map(static fn($severity) => $severity->value, $countermeasure->affectedSeverities),
            'cmf' => $countermeasure->cmf,
            'lifecycleStatus' => $countermeasure->lifecycleStatus->value,
            'implementationCost' => self::monetaryAmount($countermeasure->implementationCost),
            'expectedAnnualSavings' => $countermeasure->expectedAnnualSavings,
            'evidence' => $countermeasure->evidence,
        ];
    }

    /**
     * @param Countermeasure[] $countermeasures
     * @return list<array<string, mixed>>
     */
    public static function countermeasures(array $countermeasures): array
    {
        return array_map([self::class, 'countermeasure'], $countermeasures);
    }

    /**
     * @return array<string, mixed>
     */
    public static function roadSegment(RoadSegment $roadSegment): array
    {
        return [
            'id' => $roadSegment->id,
            'code' => $roadSegment->code,
            'lengthKm' => $roadSegment->lengthKm,
            'laneCount' => $roadSegment->laneCount,
            'functionalClass' => $roadSegment->functionalClass->value,
            'speedLimitKmh' => $roadSegment->speedLimitKmh,
            'aadt' => $roadSegment->aadt,
            'geoLocation' => [
                'wkt' => $roadSegment->geoLocation->wkt,
                'city' => $roadSegment->geoLocation->city,
                'street' => $roadSegment->geoLocation->street,
            ],
        ];
    }

    /**
     * @param RoadSegment[] $segments
     * @return list<array<string, mixed>>
     */
    public static function roadSegments(array $segments): array
    {
        return array_map([self::class, 'roadSegment'], $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public static function intersection(Intersection $intersection): array
    {
        return [
            'id' => $intersection->id,
            'code' => $intersection->code,
            'controlType' => $intersection->controlType->value,
            'numberOfLegs' => $intersection->numberOfLegs,
            'hasCameras' => $intersection->hasCameras,
            'aadt' => $intersection->aadt,
            'spfModelReference' => $intersection->spfModelReference,
            'geoLocation' => [
                'wkt' => $intersection->geoLocation->wkt,
                'city' => $intersection->geoLocation->city,
                'street' => $intersection->geoLocation->street,
            ],
        ];
    }

    /**
     * @param Intersection[] $intersections
     * @return list<array<string, mixed>>
     */
    public static function intersections(array $intersections): array
    {
        return array_map([self::class, 'intersection'], $intersections);
    }

    /**
     * @return array<string, mixed>
     */
    public static function hotspot(Hotspot $hotspot): array
    {
        return [
            'id' => $hotspot->id,
            'location' => $hotspot->location instanceof RoadSegment
                ? ['type' => 'road_segment', 'data' => self::roadSegment($hotspot->location)]
                : ['type' => 'intersection', 'data' => self::intersection($hotspot->location)],
            'period' => self::timePeriod($hotspot->period),
            'observedCrashes' => self::observedCrashes($hotspot->observedCrashes),
            'expectedCrashes' => $hotspot->expectedCrashes,
            'riskScore' => $hotspot->riskScore,
            'status' => $hotspot->status->value,
            'screeningParameters' => $hotspot->screeningParameters,
        ];
    }

    /**
     * @param Hotspot[] $hotspots
     * @return list<array<string, mixed>>
     */
    public static function hotspots(array $hotspots): array
    {
        return array_map([self::class, 'hotspot'], $hotspots);
    }


    /**
     * @return array<string, mixed>
     */
    public static function timePeriod(TimePeriod $period): array
    {
        return [
            'startDate' => $period->startDate->format('c'),
            'endDate' => $period->endDate->format('c'),
            'durationDays' => $period->getDurationInDays(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function monetaryAmount(MonetaryAmount $amount): array
    {
        return [
            'amount' => $amount->amount,
            'currency' => $amount->currency,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function observedCrashes(ObservedCrashes $observedCrashes): array
    {
        return $observedCrashes->toArray();
    }

    // Serialization helpers only – no DTO builders kept here.
}

