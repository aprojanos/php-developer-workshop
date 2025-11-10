<?php

namespace SharedKernel\ValueObject;

use SharedKernel\Enum\RoadClassification;

final readonly class RoadSegmentApplicabilityRules
{
    /**
     * @param array<RoadClassification> $roadClassifications
     */
    public function __construct(
        public array $roadClassifications
    ) {}

    /**
     * Check if the rules apply to a road segment with given classification.
     */
    public function appliesTo(?RoadClassification $roadClassification): bool
    {
        if ($roadClassification !== null && !empty($this->roadClassifications)) {
            return in_array($roadClassification, $this->roadClassifications, true);
        }

        // If no classifications specified, apply to all
        // If classification is null but rules exist, return false for safety
        return empty($this->roadClassifications);
    }
}

