<?php

namespace SharedKernel\Enum;

enum CauseFactor: string {
    case SPEEDING = 'speeding';
    case DISTRACTED_DRIVING = 'distracted_driving';
    case ALCOHOL = 'alcohol';
    case RED_LIGHT_VIOLATION = 'red_light_violation';
    case STOP_SIGN_VIOLATION = 'stop_sign_violation';
    case IMPROPER_LANE_CHANGE = 'improper_lane_change';
    case FOLLOWING_TOO_CLOSE = 'following_too_close';
    case VEHICLE_FAILURE = 'vehicle_failure';
    case ROAD_CONDITIONS = 'road_conditions';
    case WEATHER = 'weather';
    case OTHER = 'other';
}

