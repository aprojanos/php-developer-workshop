<?php

namespace SharedKernel\Model;

use SharedKernel\Enum\TargetType;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\LifecycleStatus;
use SharedKernel\ValueObject\MonetaryAmount;

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
