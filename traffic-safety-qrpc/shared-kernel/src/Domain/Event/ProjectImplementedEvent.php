<?php
namespace SharedKernel\Domain\Event;

use SharedKernel\Model\Project;

final class ProjectImplementedEvent implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly Project $project
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}

