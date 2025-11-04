<?php
namespace App\Contract;

use App\Model\Countermeasure;

interface CountermeasureRepositoryInterface
{
    public function save(Countermeasure $countermeasure): void;
    /** @return Countermeasure[] */
    public function all(): array;
    public function findById(int $id): ?Countermeasure;
    public function update(Countermeasure $countermeasure): void;
    public function delete(int $id): void;
}
