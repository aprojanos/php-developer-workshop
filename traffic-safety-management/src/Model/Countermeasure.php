<?php

namespace App\Model;

use App\Enum\TargetType;
use App\Enum\CollisionType;
use App\Enum\InjurySeverity;
use App\Enum\LifecycleStatus;
use App\ValueObject\MonetaryAmount;

abstract readonly class Countermeasure
{
    /**
     * @param int $id
     * @param string $name
     * @param array<CollisionType> $affectedCollisionTypes
     * @param array<InjurySeverity> $affectedSeverities
     * @param float $cmf
     * @param LifecycleStatus $lifecycleStatus
     * @param MonetaryAmount $implementationCost
     * @param float|null $expectedAnnualSavings
     * @param string|null $evidence
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $affectedCollisionTypes,
        public readonly array $affectedSeverities,
        public readonly float $cmf,
        public readonly LifecycleStatus $lifecycleStatus,
        public readonly MonetaryAmount $implementationCost,
        public readonly ?float $expectedAnnualSavings = null,
        public readonly ?string $evidence = null
    ) {}

    abstract public function getTargetType(): TargetType;
}
