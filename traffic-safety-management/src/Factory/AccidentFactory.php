<?php
namespace App\Factory;

use App\Model\AccidentInjury;
use App\Model\AccidentPDO;
use App\Model\AccidentBase;
use App\Enum\AccidentType;
use App\Enum\InjurySeverity;

final class AccidentFactory
{
    public static function create(array $data): AccidentBase
    {
        $id = $data['id'] ?? random_int(1000, 9999);
        $type = !empty($data['type']) ? AccidentType::from($data['type']) : AccidentType::PDO;
        return match ($type) {
            AccidentType::INJURY => new AccidentInjury(
                id: $id,
                occurredAt: new \DateTimeImmutable($data['occurredAt'] ?? 'now'),
                location: $data['location'] ?? 'unknown',
                severity: !empty($data['severity']) ? InjurySeverity::from($data['severity']) : InjurySeverity::MINOR,
                cost: (float)($data['cost'] ?? 0),
                roadSegmentId: $data['roadSegmentId'] ?? null,
                intersectionId: $data['intersectionId'] ?? null
            ),
            AccidentType::PDO => new AccidentPDO(
                id: $id,
                occurredAt: new \DateTimeImmutable($data['occurredAt'] ?? 'now'),
                location: $data['location'] ?? 'unknown',
                severity: null,
                cost: (float)($data['cost'] ?? 0),
                roadSegmentId: $data['roadSegmentId'] ?? null,
                intersectionId: $data['intersectionId'] ?? null
            ),
        };
    }
}
