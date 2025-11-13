<?php

namespace SharedKernel\Domain\Event;

use SharedKernel\Model\RoadSegment;

final class RoadSegmentCreatedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly RoadSegment $roadSegment
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getRoadSegment(): RoadSegment
    {
        return $this->roadSegment;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}


