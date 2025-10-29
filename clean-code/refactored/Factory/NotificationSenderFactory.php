<?php
declare(strict_types=1);

namespace Refactored\Factory;

use Refactored\Contract\NotificationSenderInterface;
use Refactored\Model\NotificationType;
use Refactored\Sender\EmailSender;
use Refactored\Sender\SmsSender;
use InvalidArgumentException;

// A Factory Pattern. Felelős a megfelelő stratégia (Sender) létrehozásáért.
class NotificationSenderFactory
{
    // PHP 8.0: match kifejezés
    public function createSender(NotificationType $type): NotificationSenderInterface
    {
        // Egy DI konténer esetén itt a konténertől kérnénk el a service-t.
        // Most egyszerűen példányosítunk.
        return match ($type) {
            NotificationType::Email => new EmailSender(),
            NotificationType::Sms   => new SmsSender(),
            // Ha az enum minden esetét lefedtük, nincs szükség 'default'-ra
        };
    }
}
