<?php

namespace SharedKernel\Enum;

enum CollisionType: string {
    case REAR_END = 'rear_end';
    case SIDE = 'side';
    case HEAD_ON = 'head_on';
    case SIDESWIPE = 'sideswipe';
    case SINGLE_VEHICLE = 'single_vehicle';
    case ANGLE = 'angle';
    case OTHER = 'other';
}

