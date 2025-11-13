<?php

namespace SharedKernel\Enum;

enum LifecycleStatus: string {
    case PROPOSED = 'proposed';
    case APPROVED = 'approved';
    case IMPLEMENTED = 'implemented';
}

