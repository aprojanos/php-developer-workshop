<?php

namespace App\DTO;

use App\Enum\CollisionType;
use App\Enum\InjurySeverity;
use App\Enum\LifecycleStatus;
use App\Enum\TargetType;

final readonly class CountermeasureHotspotFilterDTO
{
    public TargetType $targetType;

    /** @var array<CollisionType> */
    public array $affectedCollisionTypes;

    /** @var array<InjurySeverity> */
    public array $affectedSeverities;

    /** @var array<LifecycleStatus> */
    public array $allowedStatuses;

    /**
     * @param array<string|CollisionType> $affectedCollisionTypes
     * @param array<string|InjurySeverity> $affectedSeverities
     * @param array<string|LifecycleStatus>|null $allowedStatuses
     */
    public function __construct(
        TargetType $targetType,
        array $affectedCollisionTypes = [],
        array $affectedSeverities = [],
        ?array $allowedStatuses = null
    ) {
        $this->targetType = $targetType;
        $this->affectedCollisionTypes = array_map(
            fn ($type) => $type instanceof CollisionType ? $type : CollisionType::from($type),
            $affectedCollisionTypes
        );
        $this->affectedSeverities = array_map(
            fn ($severity) => $severity instanceof InjurySeverity ? $severity : InjurySeverity::from($severity),
            $affectedSeverities
        );
        if ($allowedStatuses !== null) {
            $this->allowedStatuses = array_map(
                fn ($status) => $status instanceof LifecycleStatus ? $status : LifecycleStatus::from($status),
                $allowedStatuses
            );
        } else {
            $this->allowedStatuses = [
                LifecycleStatus::APPROVED,
                LifecycleStatus::IMPLEMENTED,
            ];
        }
    }
}

