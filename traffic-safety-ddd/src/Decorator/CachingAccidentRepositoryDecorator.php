<?php
namespace App\Decorator;

use SharedKernel\Contract\AccidentRepositoryInterface;
use App\Model\AccidentBase;
use SharedKernel\DTO\AccidentLocationDTO;
use App\ValueObject\TimePeriod;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\WeatherCondition;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;

/**
 * Simple caching decorator for AccidentRepositoryInterface.
 * Caches the result of all() for a period (TTL seconds) if provided.
 */
final class CachingAccidentRepositoryDecorator implements AccidentRepositoryInterface
{
    private ?array $cache = null;
    private ?float $cacheCreatedAt = null;

    /**
     * @param AccidentRepositoryInterface $inner
     * @param int|null $ttl seconds to keep cache; null = forever until invalidated
     */
    public function __construct(private AccidentRepositoryInterface $inner, private ?int $ttl = 60)
    {
    }

    public function save(AccidentBase $accident): void
    {
        $this->inner->save($accident);
        $this->invalidate();
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            if ($this->ttl === null) {
                echo "Caching decorator: read from cache" . PHP_EOL;
                return $this->cache;
            }
            if ($this->cacheCreatedAt !== null && (time() - $this->cacheCreatedAt) < $this->ttl) {
                echo "Caching decorator: read from cache" . PHP_EOL;
                return $this->cache;
            }
        }

        echo "Caching decorator: read from repository" . PHP_EOL;
        $this->cache = $this->inner->all();
        $this->cacheCreatedAt = time();
        return $this->cache;
    }

    public function findById(int $id): ?AccidentBase
    {
        // try fast path using cache
        $items = $this->all();
        foreach ($items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }
        // fallback to inner
        return $this->inner->findById($id);
    }

    public function update(AccidentBase $accident): void
    {
        $this->inner->update($accident);
        $this->invalidate();
    }

    public function delete(int $id): void
    {
        $this->inner->delete($id);
        $this->invalidate();
    }

    /** @return AccidentBase[] */
    public function findByLocation(AccidentLocationDTO $location): array
    {
        // Delegate to inner repository
        // Could potentially use cache, but would need more complex caching strategy
        return $this->inner->findByLocation($location);
    }

    /** @return AccidentBase[] */
    public function search(
        ?TimePeriod $occurredAtInterval = null,
        ?AccidentLocationDTO $location = null,
        ?InjurySeverity $severity = null,
        ?AccidentType $type = null,
        ?CollisionType $collisionType = null,
        ?CauseFactor $causeFactor = null,
        ?WeatherCondition $weatherCondition = null,
        ?RoadCondition $roadCondition = null,
        ?VisibilityCondition $visibilityCondition = null,
        ?int $injuredPersonsCount = null
    ): array {
        // Delegate to inner repository
        // Could potentially use cache with complex key generation, but for simplicity delegate
        return $this->inner->search(
            $occurredAtInterval,
            $location,
            $severity,
            $type,
            $collisionType,
            $causeFactor,
            $weatherCondition,
            $roadCondition,
            $visibilityCondition,
            $injuredPersonsCount
        );
    }

    private function invalidate(): void
    {
        $this->cache = null;
        $this->cacheCreatedAt = null;
    }
}
