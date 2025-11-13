<?php
namespace SharedKernel\Domain\Event;

interface DomainEventInterface
{
    public function occurredOn(): \DateTimeImmutable;
}

