<?php

namespace SharedKernel\Contract;

use App\Model\RoadSegment;

interface RoadSegmentRepositoryInterface
{
    public function save(RoadSegment $roadSegment): void;
    /** @return RoadSegment[] */
    public function all(): array;
    public function findById(int $id): ?RoadSegment;
    public function update(RoadSegment $roadSegment): void;
    public function delete(int $id): void;
}

