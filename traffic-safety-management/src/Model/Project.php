<?php

namespace App\Model;

use App\Enum\ProjectStatus;
use App\ValueObject\TimePeriod;
use App\ValueObject\MonetaryAmount;

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

