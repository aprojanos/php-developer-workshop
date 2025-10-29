<?php
declare(strict_types=1);

namespace Refactored\Sender;

use Refactored\Contract\NotificationSenderInterface;
use Refactored\Model\User;

// Az egyik konkrét stratégia. Csak az e-mail küldéssel foglalkozik. (SRP)
class EmailSender implements NotificationSenderInterface
{
    public function send(User $user, string $message): bool
    {
        echo "Sending MODERN EMAIL to " . $user->email . ": " . $message . PHP_EOL;
        // mail($user->email, 'Értesítés', $message);
        return true;
    }
}
