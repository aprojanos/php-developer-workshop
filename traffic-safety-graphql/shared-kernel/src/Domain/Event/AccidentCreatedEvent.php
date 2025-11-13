<?php
namespace SharedKernel\Domain\Event;

use SharedKernel\Model\AccidentBase;

final class AccidentCreatedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly AccidentBase $accident
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getAccident(): AccidentBase
    {
        return $this->accident;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}

