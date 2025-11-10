<?php

namespace SharedKernel\DTO;

use App\ValueObject\TimePeriod;

final readonly class HotspotSearchDTO
{
    public function __construct(
        public ?TimePeriod $period = null,
        public ?int $roadSegmentId = null,
        public ?int $intersectionId = null,
        public ?string $status = null,
        public ?float $minRiskScore = null,
        public ?float $maxRiskScore = null,
        public ?float $minExpectedCrashes = null,
        public ?float $maxExpectedCrashes = null
    ) {}

    /**
     * Create from array with optional date range strings
     *
     * @param array{
     *     period?: TimePeriod|array{startDate: string, endDate: string},
     *     roadSegmentId?: int,
     *     intersectionId?: int,
     *     status?: string,
     *     minRiskScore?: float,
     *     maxRiskScore?: float,
     *     minExpectedCrashes?: float,
     *     maxExpectedCrashes?: float
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $period = null;
        if (isset($data['period'])) {
            if (is_array($data['period'])) {
                $period = new TimePeriod(
                    new \DateTimeImmutable($data['period']['startDate']),
                    new \DateTimeImmutable($data['period']['endDate'])
                );
            } elseif ($data['period'] instanceof TimePeriod) {
                $period = $data['period'];
            }
        }

        return new self(
            period: $period,
            roadSegmentId: $data['roadSegmentId'] ?? null,
            intersectionId: $data['intersectionId'] ?? null,
            status: $data['status'] ?? null,
            minRiskScore: isset($data['minRiskScore']) ? (float)$data['minRiskScore'] : null,
            maxRiskScore: isset($data['maxRiskScore']) ? (float)$data['maxRiskScore'] : null,
            minExpectedCrashes: isset($data['minExpectedCrashes']) ? (float)$data['minExpectedCrashes'] : null,
            maxExpectedCrashes: isset($data['maxExpectedCrashes']) ? (float)$data['maxExpectedCrashes'] : null,
        );
    }
}
