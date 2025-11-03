<?php
namespace App\Service;

use App\Contract\AccidentRepositoryInterface;
use App\Contract\LoggerInterface;
use App\Contract\NotifierInterface;
use App\Model\AccidentBase;

final class AccidentService
{
    public function __construct(
        private AccidentRepositoryInterface $repository,
        private CostEstimatorStrategyInterface $estimator,
        private ?LoggerInterface $logger = null,
        private ?NotifierInterface $notifier = null,
    ) {}

    public function create(AccidentBase $accident): void
    {
        $this->repository->save($accident);

        // log
        $this->logger?->info('Accident created', [
            'id' => $accident->id,
            'type' => $accident->type->value ?? null,
            'cost' => $accident->cost
        ]);

        // notify external system / email
        $this->notifier?->notify([
            'id' => $accident->id,
            'occurredAt' => $accident->occurredAt->format('c'),
            'location' => $accident->location,
            'type' => $accident->type->value ?? null,
            'cost' => $accident->cost,
        ]);
    }

    public function totalEstimatedCost(): float
    {
        $sum = 0.0;
        foreach ($this->repository->all() as $a) {
            $sum += $this->estimator->estimate($a);
        }
        return $sum;
    }
}
