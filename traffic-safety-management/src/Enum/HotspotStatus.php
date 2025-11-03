<?php

namespace App\Enum;

enum HotspotStatus: string {
    case OPEN = 'open';
    case REVIEWED = 'reviewed';
    case ADDRESSED = 'addressed';
}

