<?php
namespace App\Decorator;

use App\Contract\AccidentRepositoryInterface;
use App\Model\AccidentBase;

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

    private function invalidate(): void
    {
        $this->cache = null;
        $this->cacheCreatedAt = null;
    }
}
