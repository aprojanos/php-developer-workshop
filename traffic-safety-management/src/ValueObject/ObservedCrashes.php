<?php

namespace App\ValueObject;

use App\Enum\AccidentType;

final readonly class ObservedCrashes
{
    /**
     * @var array<AccidentType, int>
     */
    private array $crashes;

    /**
     * @param array<AccidentType, int>|array<string|AccidentType, int> $crashes
     */
    public function __construct(array $crashes)
    {
        $validated = [];
        foreach ($crashes as $type => $count) {
            if (!$type instanceof AccidentType) {
                throw new \InvalidArgumentException('All keys must be AccidentType instances');
            }
            if (!is_int($count) || $count < 0) {
                throw new \InvalidArgumentException('All values must be non-negative integers');
            }
            $validated[$type] = $count;
        }
        $this->crashes = $validated;
    }

    /**
     * Get the count for a specific accident type.
     */
    public function getCount(AccidentType $type): int
    {
        return $this->crashes[$type] ?? 0;
    }

    /**
     * Check if a specific accident type has crashes recorded.
     */
    public function hasType(AccidentType $type): bool
    {
        return isset($this->crashes[$type]);
    }

    /**
     * Get all accident types that have crashes recorded.
     *
     * @return array<AccidentType>
     */
    public function getTypes(): array
    {
        return array_keys($this->crashes);
    }

    /**
     * Get the total count of all crashes.
     */
    public function getTotalCount(): int
    {
        return array_sum($this->crashes);
    }

    /**
     * Get the crashes as an array.
     *
     * @return array<AccidentType, int>
     */
    public function toArray(): array
    {
        return $this->crashes;
    }

    /**
     * Check if there are any crashes recorded.
     */
    public function isEmpty(): bool
    {
        return empty($this->crashes);
    }

    /**
     * Get the count of different accident types.
     */
    public function count(): int
    {
        return count($this->crashes);
    }

    /**
     * Iterate over crashes.
     *
     * @return \Traversable<AccidentType, int>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->crashes);
    }
}

