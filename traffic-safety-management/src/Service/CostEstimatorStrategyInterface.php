<?php
namespace App\Service;

use App\Model\AccidentBase;

interface CostEstimatorStrategyInterface
{
    public function estimate(AccidentBase $accident): float;
}
