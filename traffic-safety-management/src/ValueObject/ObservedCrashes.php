<?php

namespace App\ValueObject;

use App\Enum\AccidentType;

final readonly class ObservedCrashes implements \IteratorAggregate
{
    /**
     * @var array<int, array{type: AccidentType, count: int}>
     */
    private array $entries;

    /**
     * @param array<string|AccidentType, int> $crashes
     */
    public function __construct(array $crashes)
    {
        $entries = [];
        foreach ($crashes as $type => $count) {
            $enum = null;
            if ($type instanceof AccidentType) {
                $enum = $type;
            } elseif (is_string($type)) {
                $enum = AccidentType::from($type);
            }

            if ($enum === null) {
                throw new \InvalidArgumentException('Observed crash keys must be AccidentType instances or strings.');
            }

            if (!is_int($count) || $count < 0) {
                throw new \InvalidArgumentException('Observed crash counts must be non-negative integers.');
            }

            $entries[] = [
                'type' => $enum,
                'count' => $count,
            ];
        }

        $this->entries = $entries;
    }

    public function getCount(AccidentType $type): int
    {
        foreach ($this->entries as $entry) {
            if ($entry['type'] === $type) {
                return $entry['count'];
            }
        }

        return 0;
    }

    public function hasType(AccidentType $type): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<AccidentType>
     */
    public function getTypes(): array
    {
        return array_map(fn (array $entry) => $entry['type'], $this->entries);
    }

    public function getTotalCount(): int
    {
        return array_sum(array_map(fn (array $entry) => $entry['count'], $this->entries));
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->toStringIndexedArray();
    }

    /**
     * @return array<string, int>
     */
    public function toStringIndexedArray(): array
    {
        $result = [];
        foreach ($this->entries as $entry) {
            $result[$entry['type']->value] = $entry['count'];
        }
        return $result;
    }

    /**
     * @return array<int, array{type: AccidentType, count: int}>
     */
    public function toTypeCountPairs(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return empty($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return \Traversable<string, int>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->entries as $entry) {
            yield $entry['type']->value => $entry['count'];
        }
    }
}

