<?php
namespace App\Service;

use SharedKernel\Contract\CountermeasureRepositoryInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\DTO\CountermeasureHotspotFilterDTO;
use App\Model\Countermeasure;

final class CountermeasureService
{
    public function __construct(
        private CountermeasureRepositoryInterface $repository,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Create a new countermeasure
     *
     * @param Countermeasure $countermeasure The countermeasure to create
     * @return void
     */
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

    /**
     * Find a countermeasure by its ID
     *
     * @param int $id The countermeasure ID
     * @return Countermeasure|null The countermeasure or null if not found
     */
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
     * Find countermeasures suitable for a hotspot scenario
     *
     * @param CountermeasureHotspotFilterDTO $filter Filter criteria
     * @return Countermeasure[] Matching countermeasures sorted by CMF desc
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

    /**
     * Update an existing countermeasure
     *
     * @param Countermeasure $countermeasure The countermeasure to update
     * @return void
     */
    public function update(Countermeasure $countermeasure): void
    {
        // Check if countermeasure exists
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

    /**
     * Delete a countermeasure by its ID
     *
     * @param int $id The countermeasure ID
     * @return void
     */
    public function delete(int $id): void
    {
        // Check if countermeasure exists
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
     * Get all countermeasures
     *
     * @return Countermeasure[] Array of all countermeasures
     */
    public function all(): array
    {
        return $this->repository->all();
    }
}
