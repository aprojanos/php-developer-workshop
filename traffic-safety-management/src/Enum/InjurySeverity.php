<?php
namespace App\Enum;

enum InjurySeverity: string {
    case MINOR = 'minor';
    case SERIOUS = 'serious';
    case SEVERE = 'severe';
    case FATAL = 'fatal';
}
