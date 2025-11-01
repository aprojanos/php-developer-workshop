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
    public function createSender(NotificationType $type): NotificationSenderInterface
    {
        return match ($type) {
            NotificationType::Email => new EmailSender(),
            NotificationType::Sms   => new SmsSender(),
            // Ha az enum minden esetét lefedtük, nincs szükség 'default'-ra
        };
    }
}
