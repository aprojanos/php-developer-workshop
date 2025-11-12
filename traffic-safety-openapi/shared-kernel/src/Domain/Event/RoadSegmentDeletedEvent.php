<?php

namespace SharedKernel\Domain\Event;

final class RoadSegmentDeletedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $roadSegmentId
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getRoadSegmentId(): int
    {
        return $this->roadSegmentId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}


