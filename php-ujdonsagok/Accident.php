<?php
enum AccidentType: string {
    case PDO = 'property_damage_only';
    case INJURY = 'injury';
}

enum AccidentSeverity: string {
    case MINOR = 'minor';
    case MODERATE = 'moderate';
    case SEVERE = 'severe';
    case FATAL = 'fatal';
}

class Accident {
    
    public function __construct(
        public protected(set) int $id,
        public protected(set) AccidentType $type = AccidentType::INJURY,
        public AccidentSeverity $severity = AccidentSeverity::MINOR,
        float $cost = 0.0
    )
    {
        $this->cost = $cost;
    }


    public float $cost {
        get {

            if ($this->type == AccidentType::PDO) {
                return $this->cost;
            }

            $cost = 0;
            switch($this->severity) {
                case AccidentSeverity::MODERATE:
                    $cost = 10000;
                    break;
                case AccidentSeverity::SEVERE:
                    $cost = 20000;
                    break;
                case AccidentSeverity::FATAL:
                    $cost = 50000;
                    break;
                default:
                    $cost = 1000;
                    break;

            }
            return $cost;
        }

        set (float $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException("Cost can't be negative");
            }
            $this->cost = $value;
        }
    }
}
