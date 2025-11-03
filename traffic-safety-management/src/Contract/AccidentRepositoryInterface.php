<?php
namespace App\Contract;

use App\Model\AccidentBase;

interface AccidentRepositoryInterface
{
    public function save(AccidentBase $accident): void;
    /** @return AccidentBase[] */
    public function all(): array;
    public function findById(int $id): ?AccidentBase;
}
