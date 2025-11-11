<?php

namespace SharedKernel\Domain\Event;

use SharedKernel\Model\Intersection;

final class IntersectionUpdatedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly Intersection $intersection
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getIntersection(): Intersection
    {
        return $this->intersection;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}


