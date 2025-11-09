<?php

namespace App\DTO;

use App\Enum\LocationType;
use App\ValueObject\TimePeriod;

final readonly class HotspotScreeningDTO
{
    public function __construct(
        public LocationType $locationType,
        public float $threshold,
        public ?TimePeriod $period = null,
    ) {}

    /**
     * @param array{
     *     locationType: LocationType|string,
     *     threshold: float|int,
     *     period?: TimePeriod|array{startDate: string, endDate: string}
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $locationType = $data['locationType'];
        if (!$locationType instanceof LocationType) {
            $locationType = LocationType::from(strtolower((string)$locationType));
        }

        $threshold = (float) $data['threshold'];

        $period = null;
        if (isset($data['period'])) {
            $periodData = $data['period'];

            if ($periodData instanceof TimePeriod) {
                $period = $periodData;
            } elseif (is_array($periodData)) {
                $period = new TimePeriod(
                    startDate: new \DateTimeImmutable($periodData['startDate']),
                    endDate: new \DateTimeImmutable($periodData['endDate']),
                );
            }
        }

        return new self(
            locationType: $locationType,
            threshold: $threshold,
            period: $period,
        );
    }
}

