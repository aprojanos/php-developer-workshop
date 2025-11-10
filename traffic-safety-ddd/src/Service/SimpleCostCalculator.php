<?php
namespace App\Service;

use SharedKernel\Model\AccidentBase;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Contract\CostCalculatorStrategyInterface;

final class SimpleCostCalculator implements CostCalculatorStrategyInterface
{
    public function calculate(AccidentBase $accident): float
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
