<?php

namespace App\Enum;

enum IntersectionControlType: string {
    case TRAFFIC_LIGHT = 'traffic_light';
    case PRIORITY = 'priority';
    case SIGNALLED = 'signalled';
    case ROUNDABOUT = 'roundabout';
}

