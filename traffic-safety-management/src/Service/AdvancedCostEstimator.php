<?php
namespace App\Service;

use App\Model\AccidentBase;
use App\Enum\InjurySeverity;
use App\Enum\RoadClassification;
use App\Contract\CostEstimatorStrategyInterface;

/**
 * A more advanced estimator that considers:
 * - base accident cost
 * - severity multipliers
 * - road classification (wider roads may have different exposure)
 * - a fixed overhead
 */
final class AdvancedCostEstimator implements CostEstimatorStrategyInterface
{
    public function __construct(private float $fixedOverhead = 1000.0) {}

    public function estimate(AccidentBase $accident): float
    {
        // severity multiplier
        $severityMultiplier = match($accident->severity) {
            InjurySeverity::MINOR => 1.0,
            InjurySeverity::SERIOUS => 1.6,
            InjurySeverity::SEVERE => 2.8,
            InjurySeverity::FATAL => 6.0,
            null => $accident->cost,
        };

        // road classification multiplier (if roadSegmentId is null we assume default)
        $roadMultiplier = 1.0;
        // If accident has roadSegmentId we might fetch classification from repository usually.
        // Here we make a conservative guess: higher classification => slightly higher multiplier
        if ($accident->roadSegmentId !== null) {
            # deterministic stand-in mapping from id to classification
            $guess = ($accident->roadSegmentId % 6) + 1;
            $rc = RoadClassification::from((int)$guess);
            $roadMultiplier = match($rc) {
                RoadClassification::ONE => 0.9,
                RoadClassification::TWO => 1.0,
                RoadClassification::THREE => 1.05,
                RoadClassification::FOUR => 1.1,
                RoadClassification::FIVE => 1.15,
                RoadClassification::SIX => 1.2,
            };
        }

        $base = $accident->cost;
        $estimated = ($base * $severityMultiplier * $roadMultiplier) + $this->fixedOverhead;

        // ensure non-negative
        return max(0.0, $estimated);
    }
}
