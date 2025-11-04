<?php
namespace App\Contract;

use App\Model\AccidentBase;

interface CostEstimatorStrategyInterface
{
    public function estimate(AccidentBase $accident): float;
}
