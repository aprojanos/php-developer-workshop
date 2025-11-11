<?php
namespace SharedKernel\Domain\Event;

use SharedKernel\Model\Hotspot;

final class HotspotCreatedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly Hotspot $hotspot
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getHotspot(): Hotspot
    {
        return $this->hotspot;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}

