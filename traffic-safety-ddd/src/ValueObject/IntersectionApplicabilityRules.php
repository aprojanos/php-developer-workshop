<?php

namespace App\ValueObject;

use SharedKernel\Enum\IntersectionType;
use SharedKernel\Enum\IntersectionControlType;

final readonly class IntersectionApplicabilityRules
{
    /**
     * @param array<IntersectionType> $intersectionTypes
     * @param array<IntersectionControlType> $intersectionControlTypes
     */
    public function __construct(
        public array $intersectionTypes,
        public array $intersectionControlTypes
    ) {}

    /**
     * Check if the rules apply to an intersection with given type and control type.
     */
    public function appliesTo(?IntersectionType $intersectionType, ?IntersectionControlType $intersectionControlType): bool
    {
        if ($intersectionType !== null && !empty($this->intersectionTypes) && !in_array($intersectionType, $this->intersectionTypes, true)) {
            return false;
        }

        if ($intersectionControlType !== null && !empty($this->intersectionControlTypes) && !in_array($intersectionControlType, $this->intersectionControlTypes, true)) {
            return false;
        }

        return true;
    }
}

