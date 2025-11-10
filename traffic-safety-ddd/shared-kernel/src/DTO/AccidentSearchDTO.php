<?php

namespace SharedKernel\DTO;

use App\ValueObject\TimePeriod;

final readonly class AccidentSearchDTO
{
    public function __construct(
        public ?TimePeriod $occurredAtInterval = null,
        public ?AccidentLocationDTO $location = null,
        public ?string $severity = null,
        public ?string $type = null,
        public ?string $collisionType = null,
        public ?string $causeFactor = null,
        public ?string $weatherCondition = null,
        public ?string $roadCondition = null,
        public ?string $visibilityCondition = null,
        public ?int $injuredPersonsCount = null
    ) {}

    /**
     * Create from array with optional date range strings
     *
     * @param array{
     *     occurredAtInterval?: TimePeriod|array{startDate: string, endDate: string},
     *     location?: AccidentLocationDTO|array{locationType: string, locationId: int, latitude: float, longitude: float, distanceFromStart?: float},
     *     severity?: string,
     *     type?: string,
     *     collisionType?: string,
     *     causeFactor?: string,
     *     weatherCondition?: string,
     *     roadCondition?: string,
     *     visibilityCondition?: string,
     *     injuredPersonsCount?: int
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $occurredAtInterval = null;
        if (isset($data['occurredAtInterval'])) {
            if (is_array($data['occurredAtInterval'])) {
                $occurredAtInterval = new TimePeriod(
                    new \DateTimeImmutable($data['occurredAtInterval']['startDate']),
                    new \DateTimeImmutable($data['occurredAtInterval']['endDate'])
                );
            } elseif ($data['occurredAtInterval'] instanceof TimePeriod) {
                $occurredAtInterval = $data['occurredAtInterval'];
            }
        }

        $location = null;
        if (isset($data['location'])) {
            if (is_array($data['location'])) {
                $location = new AccidentLocationDTO(
                    locationType: \SharedKernel\Enum\LocationType::from($data['location']['locationType']),
                    locationId: $data['location']['locationId'],
                    latitude: $data['location']['latitude'],
                    longitude: $data['location']['longitude'],
                    distanceFromStart: $data['location']['distanceFromStart'] ?? null
                );
            } elseif ($data['location'] instanceof AccidentLocationDTO) {
                $location = $data['location'];
            }
        }

        return new self(
            occurredAtInterval: $occurredAtInterval,
            location: $location,
            severity: $data['severity'] ?? null,
            type: $data['type'] ?? null,
            collisionType: $data['collisionType'] ?? null,
            causeFactor: $data['causeFactor'] ?? null,
            weatherCondition: $data['weatherCondition'] ?? null,
            roadCondition: $data['roadCondition'] ?? null,
            visibilityCondition: $data['visibilityCondition'] ?? null,
            injuredPersonsCount: $data['injuredPersonsCount'] ?? null
        );
    }
}

