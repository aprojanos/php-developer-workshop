<?php

namespace CountermeasureContext\Domain\Factory;

use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\IntersectionControlType;
use SharedKernel\Enum\IntersectionType;
use SharedKernel\Enum\LifecycleStatus;
use SharedKernel\Enum\RoadClassification;
use SharedKernel\Enum\TargetType;
use SharedKernel\Model\Countermeasure;
use SharedKernel\Model\IntersectionCountermeasure;
use SharedKernel\Model\RoadSegmentCountermeasure;
use SharedKernel\ValueObject\IntersectionApplicabilityRules;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\ValueObject\RoadSegmentApplicabilityRules;

final class CountermeasureFactory
{
    /**
     * @param array{
     *     id?: int,
     *     name: string,
     *     target_type: string|TargetType,
     *     applicability_rules?: array{
     *         intersection_types?: array<string|IntersectionType>,
     *         intersection_control_types?: array<string|IntersectionControlType>,
     *         road_classifications?: array<int|RoadClassification>
     *     },
     *     affected_collision_types?: array<string|CollisionType>,
     *     affected_severities?: array<string|InjurySeverity>,
     *     cmf?: float,
     *     lifecycle_status?: string|LifecycleStatus,
     *     implementation_cost?: float|array{amount: float, currency?: string}|MonetaryAmount,
     *     expected_annual_savings?: float|null,
     *     evidence?: string|null
     * } $data
     */
    public static function create(array $data): Countermeasure
    {
        $data['id'] = $data['id'] ?? random_int(1000, 9999);

        return self::createFromArray($data);
    }

    /**
     * @param array{
     *     id: int,
     *     name: string,
     *     target_type: string|TargetType,
     *     applicability_rules: array{
     *         intersection_types?: array<string|IntersectionType>,
     *         intersection_control_types?: array<string|IntersectionControlType>,
     *         road_classifications?: array<int|RoadClassification>
     *     },
     *     affected_collision_types: array<string|CollisionType>,
     *     affected_severities: array<string|InjurySeverity>,
     *     cmf: float,
     *     lifecycle_status: string|LifecycleStatus,
     *     implementation_cost: float|array{amount: float, currency?: string}|MonetaryAmount,
     *     expected_annual_savings?: float|null,
     *     evidence?: string|null
     * } $data
     */
    public static function createFromArray(array $data): Countermeasure
    {
        $targetType = $data['target_type'] instanceof TargetType
            ? $data['target_type']
            : TargetType::from($data['target_type']);

        $affectedCollisionTypes = self::parseCollisionTypes($data['affected_collision_types'] ?? []);
        $affectedSeverities = self::parseSeverities($data['affected_severities'] ?? []);
        $cmf = (float)($data['cmf'] ?? 1.0);
        $lifecycleStatus = $data['lifecycle_status'] instanceof LifecycleStatus
            ? $data['lifecycle_status']
            : LifecycleStatus::from($data['lifecycle_status'] ?? 'proposed');
        $implementationCost = self::parseMonetaryAmount($data['implementation_cost'] ?? 0.0);
        $expectedAnnualSavings = isset($data['expected_annual_savings']) ? (float)$data['expected_annual_savings'] : null;
        $applicabilityRules = $data['applicability_rules'] ?? [];

        return match ($targetType) {
            TargetType::INTERSECTION => new IntersectionCountermeasure(
                $data['id'],
                $data['name'],
                self::createIntersectionApplicabilityRules($applicabilityRules),
                $affectedCollisionTypes,
                $affectedSeverities,
                $cmf,
                $lifecycleStatus,
                $implementationCost,
                $expectedAnnualSavings,
                $data['evidence'] ?? null
            ),
            TargetType::ROAD_SEGMENT => new RoadSegmentCountermeasure(
                $data['id'],
                $data['name'],
                self::createRoadSegmentApplicabilityRules($applicabilityRules),
                $affectedCollisionTypes,
                $affectedSeverities,
                $cmf,
                $lifecycleStatus,
                $implementationCost,
                $expectedAnnualSavings,
                $data['evidence'] ?? null
            ),
        };
    }

    /**
     * @param array<string|IntersectionType> $types
     * @return array<IntersectionType>
     */
    private static function parseIntersectionTypes(array $types): array
    {
        return array_map(
            fn($type) => $type instanceof IntersectionType ? $type : IntersectionType::from($type),
            $types
        );
    }

    /**
     * @param array<string|IntersectionControlType> $types
     * @return array<IntersectionControlType>
     */
    private static function parseIntersectionControlTypes(array $types): array
    {
        return array_map(
            fn($type) => $type instanceof IntersectionControlType ? $type : IntersectionControlType::from($type),
            $types
        );
    }

    /**
     * @param array<int|RoadClassification> $classifications
     * @return array<RoadClassification>
     */
    private static function parseRoadClassifications(array $classifications): array
    {
        return array_map(
            fn($classification) => $classification instanceof RoadClassification
                ? $classification
                : RoadClassification::from($classification),
            $classifications
        );
    }

    /**
     * @param array<string|CollisionType> $types
     * @return array<CollisionType>
     */
    private static function parseCollisionTypes(array $types): array
    {
        return array_map(
            fn($type) => $type instanceof CollisionType ? $type : CollisionType::from($type),
            $types
        );
    }

    /**
     * @param array<string|InjurySeverity> $severities
     * @return array<InjurySeverity>
     */
    private static function parseSeverities(array $severities): array
    {
        return array_map(
            fn($severity) => $severity instanceof InjurySeverity ? $severity : InjurySeverity::from($severity),
            $severities
        );
    }

    /**
     * @param array{
     *     intersection_types?: array<string|IntersectionType>,
     *     intersection_control_types?: array<string|IntersectionControlType>
     * } $rules
     */
    private static function createIntersectionApplicabilityRules(array $rules): IntersectionApplicabilityRules
    {
        return new IntersectionApplicabilityRules(
            self::parseIntersectionTypes($rules['intersection_types'] ?? []),
            self::parseIntersectionControlTypes($rules['intersection_control_types'] ?? [])
        );
    }

    /**
     * @param array{road_classifications?: array<int|RoadClassification>} $rules
     */
    private static function createRoadSegmentApplicabilityRules(array $rules): RoadSegmentApplicabilityRules
    {
        return new RoadSegmentApplicabilityRules(
            self::parseRoadClassifications($rules['road_classifications'] ?? [])
        );
    }

    /**
     * @param float|array{amount: float, currency?: string}|MonetaryAmount $value
     */
    private static function parseMonetaryAmount(mixed $value): MonetaryAmount
    {
        if ($value instanceof MonetaryAmount) {
            return $value;
        }

        if (is_array($value)) {
            return new MonetaryAmount(
                (float)($value['amount'] ?? 0.0),
                $value['currency'] ?? 'USD'
            );
        }

        return new MonetaryAmount((float)$value, 'USD');
    }
}

