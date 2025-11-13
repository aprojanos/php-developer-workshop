<?php
namespace SharedKernel\Domain\Event;

interface EventBusInterface
{
    public function dispatch(DomainEventInterface $event): void;

    /**
     * @param class-string<DomainEventInterface> $eventClass
     * @param callable(DomainEventInterface):void $listener
     */
    public function addListener(string $eventClass, callable $listener): void;

    public function startConsuming(): void;
}

