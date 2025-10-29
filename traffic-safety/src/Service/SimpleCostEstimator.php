<?php
namespace App\Service;

use App\Model\AccidentBase;
use App\Enum\InjurySeverity;

final class SimpleCostEstimator implements CostEstimatorStrategyInterface
{
    public function estimate(AccidentBase $accident): float
    {
        return match($accident->severity) {
            InjurySeverity::MINOR => $accident->cost + 10000,
            InjurySeverity::SERIOUS => $accident->cost + 20000,
            InjurySeverity::SEVERE => $accident->cost + 40000,
            InjurySeverity::FATAL => $accident->cost + 100000,
            null => $accident->cost,
        };
    }
}
