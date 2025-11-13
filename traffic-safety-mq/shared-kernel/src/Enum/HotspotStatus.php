<?php

namespace SharedKernel\Enum;

enum HotspotStatus: string {
    case OPEN = 'open';
    case REVIEWED = 'reviewed';
    case ADDRESSED = 'addressed';
}

