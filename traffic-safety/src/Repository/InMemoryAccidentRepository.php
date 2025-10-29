<?php
namespace App\Repository;

use App\Model\AccidentBase;
use App\Contract\AccidentRepositoryInterface;

final class InMemoryAccidentRepository implements AccidentRepositoryInterface
{
    private array $accidents = [];

    public function save(AccidentBase $accident): void
    {
        $this->accidents[$accident->id] = $accident;
    }

    public function all(): array
    {
        return array_values($this->accidents);
    }

    public function findById(int $id): ?AccidentBase
    {
        return $this->accidents[$id] ?? null;
    }
}
