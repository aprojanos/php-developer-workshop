<?php
namespace App\Contract;

use App\Model\AccidentBase;

interface CostCalculatorStrategyInterface
{
    public function calculate(AccidentBase $accident): float;
}
