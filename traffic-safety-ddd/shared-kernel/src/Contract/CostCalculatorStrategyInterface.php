<?php
namespace SharedKernel\Contract;

use App\Model\AccidentBase;

interface CostCalculatorStrategyInterface
{
    public function calculate(AccidentBase $accident): float;
}
