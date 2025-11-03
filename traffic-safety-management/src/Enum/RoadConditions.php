<?php

namespace App\Enum;

enum RoadConditions: string {
    case DRY = 'dry';
    case WET = 'wet';
    case SNOW_COVERED = 'snow_covered';
    case ICE_COVERED = 'ice_covered';
    case SLUSH = 'slush';
    case LOOSE_GRAVEL = 'loose_gravel';
    case POTHOLES = 'potholes';
    case CONSTRUCTION = 'construction';
    case OTHER = 'other';
}

