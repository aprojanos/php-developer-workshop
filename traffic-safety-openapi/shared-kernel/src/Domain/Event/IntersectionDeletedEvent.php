<?php

namespace SharedKernel\Domain\Event;

final class IntersectionDeletedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $intersectionId
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getIntersectionId(): int
    {
        return $this->intersectionId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}


