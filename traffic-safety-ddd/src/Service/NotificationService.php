<?php
namespace App\Service;

use SharedKernel\Contract\NotifierInterface;
use SharedKernel\Domain\Event\DomainEventInterface;
use SharedKernel\Domain\Event\EventBusInterface;

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

        if (method_exists($event, 'getAccident')) {
            $accident = $event->getAccident();
            $payload['accident'] = [
                'id' => $accident->id,
                'type' => $accident->getType()->value,
                'occurredAt' => $accident->occurredAt->format('c'),
            ];
        }

        if (method_exists($event, 'getProject')) {
            $project = $event->getProject();
            $payload['project'] = [
                'id' => $project->id,
                'countermeasureId' => $project->countermeasureId,
                'status' => $project->status->value,
            ];
        }

        $this->notifier->notify($payload);
    }
}

