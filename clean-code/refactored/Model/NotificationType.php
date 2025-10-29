<?php
declare(strict_types=1);

namespace Refactored\Model;

// PHP 8.1: Enum a "magic string"-ek ('email', 'sms') helyett
enum NotificationType: string
{
    case Email = 'email';
    case Sms = 'sms';
}
