<?php
declare(strict_types=1);

namespace Refactored\Contract;

use Refactored\Model\User;

// A Strategy Pattern alapja: minden küldőnek ezt kell implementálnia.
interface NotificationSenderInterface
{
    public function send(User $user, string $message): bool;
}
