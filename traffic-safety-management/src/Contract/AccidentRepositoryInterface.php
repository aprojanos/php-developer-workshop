<?php
namespace App\Contract;

use App\Model\AccidentBase;
use App\DTO\AccidentLocationDTO;

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
        ?\App\Enum\InjurySeverity $severity = null,
        ?\App\Enum\AccidentType $type = null,
        ?\App\Enum\CollisionType $collisionType = null,
        ?\App\Enum\CauseFactor $causeFactor = null,
        ?\App\Enum\WeatherCondition $weatherCondition = null,
        ?\App\Enum\RoadCondition $roadCondition = null,
        ?\App\Enum\VisibilityCondition $visibilityCondition = null,
        ?int $injuredPersonsCount = null
    ): array;
}
