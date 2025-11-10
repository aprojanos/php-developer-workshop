<?php
namespace SharedKernel\Contract;

use App\Model\AccidentBase;
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
    public function search(
        ?\App\ValueObject\TimePeriod $occurredAtInterval = null,
        ?AccidentLocationDTO $location = null,
        ?\SharedKernel\Enum\InjurySeverity $severity = null,
        ?\SharedKernel\Enum\AccidentType $type = null,
        ?\SharedKernel\Enum\CollisionType $collisionType = null,
        ?\SharedKernel\Enum\CauseFactor $causeFactor = null,
        ?\SharedKernel\Enum\WeatherCondition $weatherCondition = null,
        ?\SharedKernel\Enum\RoadCondition $roadCondition = null,
        ?\SharedKernel\Enum\VisibilityCondition $visibilityCondition = null,
        ?int $injuredPersonsCount = null
    ): array;
}
