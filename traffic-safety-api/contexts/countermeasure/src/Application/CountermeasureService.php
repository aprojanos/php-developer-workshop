<?php

namespace CountermeasureContext\Application;

use SharedKernel\Contract\CountermeasureRepositoryInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\ProjectEvaluatedEvent;
use SharedKernel\DTO\CountermeasureHotspotFilterDTO;
use SharedKernel\Model\Countermeasure;

final class CountermeasureService
{
    public function __construct(
        private CountermeasureRepositoryInterface $repository,
        private ?LoggerInterface $logger = null,
        private ?EventBusInterface $eventBus = null,
    ) {
        $this->eventBus?->addListener(
            ProjectEvaluatedEvent::class,
            function (ProjectEvaluatedEvent $event): void {
                $this->recalculateCmf($event);
            }
        );
    }

    public function create(Countermeasure $countermeasure): void
    {
        $this->repository->save($countermeasure);

        $this->logger?->info('Countermeasure created', [
            'id' => $countermeasure->id,
            'name' => $countermeasure->name,
            'targetType' => $countermeasure->getTargetType()->value,
            'lifecycleStatus' => $countermeasure->lifecycleStatus->value,
        ]);
    }

    public function findById(int $id): ?Countermeasure
    {
        $countermeasure = $this->repository->findById($id);

        if ($countermeasure !== null) {
            $this->logger?->info('Countermeasure retrieved', [
                'id' => $countermeasure->id,
                'name' => $countermeasure->name,
                'targetType' => $countermeasure->getTargetType()->value,
            ]);
        }

        return $countermeasure;
    }

    /**
     * @return Countermeasure[]
     */
    public function findForHotspot(CountermeasureHotspotFilterDTO $filter): array
    {
        $countermeasures = $this->repository->findForHotspot($filter);

        $this->logger?->info('Countermeasures retrieved for hotspot applicability', [
            'targetType' => $filter->targetType->value,
            'affectedCollisionTypes' => array_map(fn($type) => $type->value, $filter->affectedCollisionTypes),
            'affectedSeverities' => array_map(fn($severity) => $severity->value, $filter->affectedSeverities),
            'allowedStatuses' => array_map(fn($status) => $status->value, $filter->allowedStatuses),
            'count' => count($countermeasures),
        ]);

        return $countermeasures;
    }

    public function update(Countermeasure $countermeasure): void
    {
        $existing = $this->repository->findById($countermeasure->id);
        if ($existing === null) {
            throw new \InvalidArgumentException("Countermeasure with ID {$countermeasure->id} not found");
        }

        $this->repository->update($countermeasure);

        $this->logger?->info('Countermeasure updated', [
            'id' => $countermeasure->id,
            'name' => $countermeasure->name,
            'targetType' => $countermeasure->getTargetType()->value,
            'lifecycleStatus' => $countermeasure->lifecycleStatus->value,
        ]);
    }

    public function delete(int $id): void
    {
        $countermeasure = $this->repository->findById($id);
        if ($countermeasure === null) {
            throw new \InvalidArgumentException("Countermeasure with ID {$id} not found");
        }

        $this->repository->delete($id);

        $this->logger?->info('Countermeasure deleted', [
            'id' => $id,
            'name' => $countermeasure->name,
            'targetType' => $countermeasure->getTargetType()->value,
        ]);
    }

    /**
     * @return Countermeasure[]
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    public function recalculateCmf(ProjectEvaluatedEvent $event): void
    {
        $project = $event->getProject();
        $accident = $event->getAccident();

        $this->logger?->info('Countermeasure CMF recalculated after project evaluation', [
            'projectId' => $project->id,
            'countermeasureId' => $project->countermeasureId,
            'accidentId' => $accident->id,
        ]);
    }
}

