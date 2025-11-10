<?php
namespace App\Model;

use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\AccidentType;

final class AccidentInjury extends AccidentBase
{
    public function getType(): AccidentType {
        return AccidentType::INJURY;
    }

    public function getSeverityLabel(): string
    {
        return $this->severity?->value ?? 'unknown';
    }
        
    /**
     * Injury accidents often require immediate attention
     * Especially severe and fatal ones
     */
    public function requiresImmediateAttention(): bool
    {
        if ($this->severity === null) {
            return false;
        }
        
        return match($this->severity) {
            InjurySeverity::FATAL => true,
            InjurySeverity::SEVERE => true,
            InjurySeverity::SERIOUS => $this->cost > 5000,
            InjurySeverity::MINOR => false,
        };
    }
    
}
