<?php
namespace App\Repository;

use App\Contract\AccidentRepositoryInterface;
use App\Model\AccidentBase;
use App\Factory\AccidentFactory;
use App\Enum\InjurySeverity;
use App\Enum\AccidentType;

/**
 * Repository that persists accidents in a CSV file.
 * Demonstrates Adapter pattern replacing legacy flat-file storage.
 */
final class FileAccidentRepository implements AccidentRepositoryInterface
{
    public function __construct(private string $path)
    {
        @mkdir(dirname($this->path), 0755, true);
    }

    public function save(AccidentBase $accident): void
    {
        $line = implode(',', [
            $accident->id,
            $accident->occurredAt->format('c'),
            str_replace(',', ';', $accident->location),
            $accident->severity?->value,
            $accident->getType()->value,
            $accident->cost,
            $accident->roadSegmentId ?? '',
            $accident->intersectionId ?? '',
        ]) . PHP_EOL;

        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }

    /** @return AccidentBase[] */
    public function all(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $rows = [];
        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $parts = str_getcsv($line, ",", '"', "\\");
            if (count($parts) < 6) {
                continue; // skip invalid line
            }
            [
                $id,
                $occurredAt,
                $location,
                $severity,
                $type,
                $cost,
                $roadSegmentId,
                $intersectionId
            ] = array_pad($parts, 8, null);

            $rows[] = AccidentFactory::create([
                'id' => (int)$id,
                'occurredAt' => $occurredAt,
                'location' => $location,
                'severity' => $severity,
                'type' => $type,
                'cost' => (float)$cost,
                'roadSegmentId' => $roadSegmentId !== '' ? (int)$roadSegmentId : null,
                'intersectionId' => $intersectionId !== '' ? (int)$intersectionId : null,
            ]);
        }
        return $rows;
    }

    public function findById(int $id): ?AccidentBase
    {
        foreach ($this->all() as $a) {
            if ($a->id === $id) {
                return $a;
            }
        }
        return null;
    }
}
