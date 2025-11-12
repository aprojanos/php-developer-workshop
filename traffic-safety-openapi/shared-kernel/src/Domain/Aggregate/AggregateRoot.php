<?php
namespace SharedKernel\Domain\Aggregate;

use SharedKernel\Domain\Event\DomainEventInterface;

abstract class AggregateRoot
{
    /**
     * @var DomainEventInterface[]
     */
    private array $recordedEvents = [];

    public function recordEvent(DomainEventInterface $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return DomainEventInterface[]
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}

