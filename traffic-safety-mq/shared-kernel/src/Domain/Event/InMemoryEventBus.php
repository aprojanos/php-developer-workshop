<?php
namespace SharedKernel\Domain\Event;

final class InMemoryEventBus implements EventBusInterface
{
    /**
     * @var array<class-string<DomainEventInterface>, list<callable>>
     */
    private array $listeners = [];

    /**
     * @var list<DomainEventInterface>
     */
    public array $dispatchedEvents = [];

    public function dispatch(DomainEventInterface $event): void
    {
        $this->dispatchedEvents[] = $event;

        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }

        foreach ($this->listeners[DomainEventInterface::class] ?? [] as $listener) {
            $listener($event);
        }
    }

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function startConsuming(): void
    {
        // No-op for the in-memory event bus.
    }
}

