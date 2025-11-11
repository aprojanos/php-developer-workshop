<?php
namespace App\Service;

use SharedKernel\Contract\NotifierInterface;
use SharedKernel\Domain\Event\AccidentCreatedEvent;
use SharedKernel\Domain\Event\DomainEventInterface;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\HotspotCreatedEvent;
use SharedKernel\Domain\Event\ProjectApprovedEvent;
use SharedKernel\Domain\Event\ProjectEvaluatedEvent;
use SharedKernel\Domain\Event\ProjectImplementedEvent;
use SharedKernel\Domain\Event\ProjectProposedEvent;

final class NotificationService
{
    public function __construct(
        private NotifierInterface $notifier,
        private EventBusInterface $eventBus
    ) {
        $this->eventBus->addListener(
            DomainEventInterface::class,
            function (DomainEventInterface $event): void {
                $this->handleEvent($event);
            }
        );
    }

    private function handleEvent(DomainEventInterface $event): void
    {
        $payload = [
            'event' => $event::class,
            'occurredOn' => $event->occurredOn()->format('c'),
        ];
        if ($event instanceof AccidentCreatedEvent || $event instanceof ProjectEvaluatedEvent) {
            $accident = $event->getAccident();
            $payload['accident'] = [
                'id' => $accident->id,
                'type' => $accident->getType()->value,
                'occurredAt' => $accident->occurredAt->format('c'),
            ];
        }

        if ($event instanceof ProjectProposedEvent
            || $event instanceof ProjectApprovedEvent
            || $event instanceof ProjectImplementedEvent
            || $event instanceof ProjectEvaluatedEvent
        ) {
            $project = $event->getProject();
            $payload['project'] = [
                'id' => $project->id,
                'countermeasureId' => $project->countermeasureId,
                'status' => $project->status->value,
            ];
        }

        if ($event instanceof HotspotCreatedEvent) {
            $hotspot = $event->getHotspot();
            $payload['hotspot'] = [
                'id' => $hotspot->id,
                'status' => $hotspot->status->value,
                'riskScore' => $hotspot->riskScore,
            ];
        }

        $this->notifier->notify($payload);
    }
}

