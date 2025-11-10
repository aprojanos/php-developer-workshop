<?php
namespace SharedKernel\Contract;

use SharedKernel\Model\AccidentBase;

interface CostCalculatorStrategyInterface
{
    public function calculate(AccidentBase $accident): float;
}
