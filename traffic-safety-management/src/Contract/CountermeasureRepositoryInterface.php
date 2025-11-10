<?php
namespace App\Contract;

use App\DTO\CountermeasureHotspotFilterDTO;
use App\Model\Countermeasure;

interface CountermeasureRepositoryInterface
{
    public function save(Countermeasure $countermeasure): void;
    /** @return Countermeasure[] */
    public function all(): array;
    public function findById(int $id): ?Countermeasure;
    /** @return Countermeasure[] */
    public function findForHotspot(CountermeasureHotspotFilterDTO $filter): array;
    public function update(Countermeasure $countermeasure): void;
    public function delete(int $id): void;
}
