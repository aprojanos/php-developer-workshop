<?php
namespace SharedKernel\Contract;

use SharedKernel\DTO\AccidentSearchCriteria;
use SharedKernel\Model\AccidentBase;
use SharedKernel\DTO\AccidentLocationDTO;

interface AccidentRepositoryInterface
{
    public function save(AccidentBase $accident): void;
    /** @return AccidentBase[] */
    public function all(): array;
    public function findById(int $id): ?AccidentBase;
    public function update(AccidentBase $accident): void;
    public function delete(int $id): void;
    /** @return AccidentBase[] */
    public function findByLocation(AccidentLocationDTO $location): array;
    /** @return AccidentBase[] */
    public function search(AccidentSearchCriteria $criteria): array;
}
