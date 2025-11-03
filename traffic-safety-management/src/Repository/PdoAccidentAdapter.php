<?php
namespace App\Repository;

use App\Model\AccidentBase;
use App\Factory\AccidentFactory;
use App\Contract\AccidentRepositoryInterface;
use App\Enum\AccidentType;

final class PdoAccidentAdapter implements AccidentRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function save(AccidentBase $accident): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO accidents (id, occurred_at, location, severity, type, cost, road_segment_id, intersection_id)
            VALUES (:id,:occurred_at,:location,:severity,:type,:cost,:road_segment_id,:intersection_id)');
        $stmt->execute([
            'id' => $accident->id,
            'occurred_at' => $accident->occurredAt->format('c'),
            'location' => $accident->location,
            'severity' => $accident->severity->value,
            'cost' => $accident->cost,
            'type' => $accident->getType(),
            'road_segment_id' => $accident->roadSegmentId,
            'intersection_id' => $accident->intersectionId,
        ]);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM accidents');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $result[] = AccidentFactory::create([
                'id' => (int)$r['id'],
                'occurredAt' => $r['occurred_at'],
                'location' => $r['location'],
                'severity' => $r['severity'],
                'cost' => (float)$r['cost'],
                'roadSegmentId' => $r['road_segment_id'] !== null ? (int)$r['road_segment_id'] : null,
                'intersectionId' => $r['intersection_id'] !== null ? (int)$r['intersection_id'] : null,
            ]);
        }
        return $result;
    }

    public function findById(int $id): ?AccidentBase
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accidents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) return null;
        return AccidentFactory::create([
            'id' => (int)$r['id'],
            'occurredAt' => $r['occurred_at'],
            'location' => $r['location'],
            'severity' => $r['severity'],
            'cost' => (float)$r['cost'],
            'roadSegmentId' => $r['road_segment_id'] !== null ? (int)$r['road_segment_id'] : null,
            'intersectionId' => $r['intersection_id'] !== null ? (int)$r['intersection_id'] : null,
        ]);
    }
}
