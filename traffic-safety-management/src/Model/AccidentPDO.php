<?php
namespace App\Model;

use App\Enum\AccidentType;

final class AccidentPDO extends AccidentBase
{

    public function getType(): AccidentType {
        return AccidentType::PDO;
    }

    public function getSeverityLabel(): string
    {
        return 'Property Damage Only';
    }
        
    /**
     * PDO accidents rarely require immediate attention
     * unless they're very expensive
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->cost > 15000;
    }
    
}
