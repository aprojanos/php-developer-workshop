<?php

namespace SharedKernel\Model;

use SharedKernel\Enum\ProjectStatus;
use SharedKernel\ValueObject\TimePeriod;
use SharedKernel\ValueObject\MonetaryAmount;

final readonly class Project
{
    public function __construct(
        public int $id,
        public int $countermeasureId,
        public int $hotspotId,
        public TimePeriod $period,
        public MonetaryAmount $expectedCost,
        public MonetaryAmount $actualCost,
        public ProjectStatus $status
    ) {}
}

