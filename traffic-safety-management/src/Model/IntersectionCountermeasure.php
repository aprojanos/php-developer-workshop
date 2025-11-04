<?php

namespace App\Model;

use App\Enum\TargetType;
use App\Enum\CollisionType;
use App\Enum\InjurySeverity;
use App\Enum\LifecycleStatus;
use App\ValueObject\IntersectionApplicabilityRules;
use App\ValueObject\MonetaryAmount;

final readonly class IntersectionCountermeasure extends Countermeasure
{
    /**
     * @param int $id
     * @param string $name
     * @param IntersectionApplicabilityRules $applicabilityRules
     * @param array<CollisionType> $affectedCollisionTypes
     * @param array<InjurySeverity> $affectedSeverities
     * @param float $cmf
     * @param LifecycleStatus $lifecycleStatus
     * @param MonetaryAmount $implementationCost
     * @param float|null $expectedAnnualSavings
     * @param string|null $evidence
     */
    public function __construct(
        int $id,
        string $name,
        public readonly IntersectionApplicabilityRules $applicabilityRules,
        array $affectedCollisionTypes,
        array $affectedSeverities,
        float $cmf,
        LifecycleStatus $lifecycleStatus,
        MonetaryAmount $implementationCost,
        ?float $expectedAnnualSavings = null,
        ?string $evidence = null
    ) {
        parent::__construct(
            $id,
            $name,
            $affectedCollisionTypes,
            $affectedSeverities,
            $cmf,
            $lifecycleStatus,
            $implementationCost,
            $expectedAnnualSavings,
            $evidence
        );
    }

    public function getTargetType(): TargetType
    {
        return TargetType::INTERSECTION;
    }
}

