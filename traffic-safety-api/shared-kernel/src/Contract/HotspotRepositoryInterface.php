<?php
namespace SharedKernel\Contract;

use SharedKernel\Model\Hotspot;
use SharedKernel\ValueObject\TimePeriod;
use SharedKernel\Enum\HotspotStatus;

interface HotspotRepositoryInterface
{
    public function save(Hotspot $hotspot): void;
    /** @return Hotspot[] */
    public function all(): array;
    public function findById(int $id): ?Hotspot;
    public function update(Hotspot $hotspot): void;
    public function delete(int $id): void;
    /** @return Hotspot[] */
    public function search(
        ?TimePeriod $period = null,
        ?int $roadSegmentId = null,
        ?int $intersectionId = null,
        ?HotspotStatus $status = null,
        ?float $minRiskScore = null,
        ?float $maxRiskScore = null,
        ?float $minExpectedCrashes = null,
        ?float $maxExpectedCrashes = null
    ): array;
}
