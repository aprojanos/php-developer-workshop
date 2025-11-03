<?php

namespace App\Enum;

enum VisibilityConditions: string {
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';
    case VERY_POOR = 'very_poor';
    case NIGHT = 'night';
    case DAWN = 'dawn';
    case DUSK = 'dusk';
    case OTHER = 'other';
}

