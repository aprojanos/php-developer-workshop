<?php
namespace SharedKernel\Domain\Event;

use SharedKernel\Model\AccidentBase;
use SharedKernel\Model\Project;

final class ProjectEvaluatedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly Project $project,
        private readonly AccidentBase $accident
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getProject(): Project
    {
        return $this->project;
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

