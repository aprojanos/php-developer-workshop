<?php
declare(strict_types=1);

namespace Refactored\Sender;

use Refactored\Contract\NotificationSenderInterface;
use Refactored\Model\User;

// A másik konkrét stratégia. Csak az SMS küldéssel foglalkozik. (SRP)
class SmsSender implements NotificationSenderInterface
{
    public function send(User $user, string $message): bool
    {
        echo "Sending MODERN SMS to " . $user->phone . ": " . $message . PHP_EOL;
        // file_get_contents('https://api.sms-gateway.com/send?to='...);
        return true;
    }
}
