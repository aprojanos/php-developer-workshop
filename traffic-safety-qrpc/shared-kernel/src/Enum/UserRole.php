<?php

namespace SharedKernel\Enum;

enum UserRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case ANALYST = 'analyst';
    case VIEWER = 'viewer';
}

