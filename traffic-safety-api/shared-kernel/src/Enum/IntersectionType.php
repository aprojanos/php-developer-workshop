<?php

namespace SharedKernel\Enum;

enum IntersectionType: string {
    case TRAFFIC_LIGHT = 'traffic_light';
    case SIGN = 'sign';
    case ROUNDABOUT = 'roundabout';
    case EQUAL_PRIORITY = 'equal_priority';
}
