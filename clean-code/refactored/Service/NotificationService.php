<?php
declare(strict_types=1);

namespace Refactored\Service;

use Refactored\Contract\UserRepositoryInterface;
use Refactored\Factory\NotificationSenderFactory;
use Refactored\Model\NotificationType;
use Refactored\Model\User;
// A fő service, ami összerakja a logikát.
// Csak absztrakcióktól függ (DIP).
class NotificationService
{
    // PHP 8.1: readonly
    // PHP 8.0: Constructor Property Promotion
    // A függőségeket "injektáljuk" (Dependency Injection).
    public function __construct(
        private readonly NotificationSenderFactory $senderFactory
    ) {}

    public function sendNotification(User $user, string $message, NotificationType $type): bool
    {
        // 1. Felelősség: Felhasználó lekérése (delegálva a Repository-nak)
        // 2. Felelősség: Üzenet formázása (itt egyszerű, de ki lehetne szervezni)
        $formattedMessage = "Kedves " . $user->name . "! Üzenet: " . $message;

        // 3. Felelősség: Megfelelő küldő kiválasztása (delegálva a Factory-nak)
        $sender = $this->senderFactory->createSender($type);

        // 4. Felelősség: Küldés (delegálva a Strategy-nek)
        return $sender->send($user, $formattedMessage);
    }
}
