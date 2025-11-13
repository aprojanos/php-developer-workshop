<?php

namespace SharedKernel\Enum;

enum ProjectStatus: string {
    case PROPOSED = 'proposed';
    case APPROVED = 'approved';
    case IMPLEMENTED = 'implemented';
    case CLOSED = 'closed';
}

