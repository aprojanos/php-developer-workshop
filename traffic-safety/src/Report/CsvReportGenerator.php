<?php
namespace App\Report;

use App\Contract\AccidentRepositoryInterface;

/**
 * CSV report generator that writes accidents to CSV.
 * If a repository is provided, it will use repository->all(), otherwise accepts items via constructor.
 */
final class CsvReportGenerator extends AbstractReportGenerator
{
    private array $items;

    public function __construct(private ?AccidentRepositoryInterface $repository = null, array $items = [])
    {
        $this->items = $items;
    }

    protected function getItems(): array
    {
        if ($this->repository !== null) {
            return $this->repository->all();
        }
        return $this->items;
    }

    protected function header(): string
    {
        // CSV header
        return "id,occurred_at,location,severity,type,cost,road_segment_id,intersection_id\n";
    }

    protected function formatItem(mixed $item): string
    {
        // Expecting either array-like or an object with properties; support Accident objects
        if (is_array($item)) {
            $row = $item;
        } else {
            // attempt to read properties
            $row = [
                $item->id ?? '',
                $item->occurredAt->format('c') ?? '',
                str_replace('\n', ' ', $item->location ?? ''),
                $item->severity->value ?? '',
                $item->type->value ?? '',
                $item->cost ?? '',
                $item->roadSegmentId ?? '',
                $item->intersectionId ?? '',
            ];
        }

        // Escape CSV fields
        $escaped = array_map(function($v) {
            if ($v === null) return '';
            $s = (string)$v;
            // wrap in quotes if contains special chars
            if (strpbrk($s, ",\n\r\"") !== false) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            return $s;
        }, $row);

        return implode(',', $escaped) . "\n";
    }
}
