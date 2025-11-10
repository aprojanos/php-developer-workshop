<?php

use App\Factory\AccidentFactory;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use SharedKernel\Contract\NotifierInterface;
use SharedKernel\Domain\Event\AccidentCreatedEvent;
use SharedKernel\Domain\Event\ProjectEvaluatedEvent;
use SharedKernel\Domain\Event\InMemoryEventBus;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\ProjectStatus;
use SharedKernel\Model\Project;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\ValueObject\TimePeriod;

final class NotificationServiceTest extends TestCase
{
    public function testNotifierInvokedForAccidentEvent(): void
    {
        $accident = AccidentFactory::create([
            'id' => 9001,
            'occurredAt' => '2025-05-15 11:20:00',
            'type' => AccidentType::INJURY->value,
            'severity' => InjurySeverity::SERIOUS->value,
            'cost' => 4500.0,
        ]);

        $eventBus = new InMemoryEventBus();

        /** @var NotifierInterface&\PHPUnit\Framework\MockObject\MockObject $notifier */
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(static function (array $payload) use ($accident): bool {
                return $payload['event'] === AccidentCreatedEvent::class
                    && $payload['accident']['id'] === $accident->id
                    && $payload['accident']['type'] === $accident->getType()->value;
            }));

        $service = new NotificationService($notifier, $eventBus);
        $this->assertInstanceOf(NotificationService::class, $service);

        $eventBus->dispatch(new AccidentCreatedEvent($accident));
    }

    public function testNotifierIncludesProjectContextForProjectEvents(): void
    {
        $accident = AccidentFactory::create([
            'id' => 810,
            'occurredAt' => '2025-07-01 08:00:00',
            'type' => AccidentType::INJURY->value,
            'severity' => InjurySeverity::SEVERE->value,
        ]);

        $project = new Project(
            id: 120,
            countermeasureId: 88,
            hotspotId: 42,
            period: new TimePeriod(
                new \DateTimeImmutable('2025-01-01'),
                new \DateTimeImmutable('2025-12-31')
            ),
            expectedCost: new MonetaryAmount(150000.0),
            actualCost: new MonetaryAmount(142500.0),
            status: ProjectStatus::APPROVED
        );

        $eventBus = new InMemoryEventBus();

        /** @var NotifierInterface&\PHPUnit\Framework\MockObject\MockObject $notifier */
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(static function (array $payload) use ($project, $accident): bool {
                return $payload['event'] === ProjectEvaluatedEvent::class
                    && $payload['project']['id'] === $project->id
                    && $payload['accident']['id'] === $accident->id;
            }));

        $service = new NotificationService($notifier, $eventBus);
        $this->assertInstanceOf(NotificationService::class, $service);

        $eventBus->dispatch(new ProjectEvaluatedEvent($project, $accident));
    }
}

