<?php

namespace NotificationContext\Infrastructure\Notifier;

use SharedKernel\Contract\NotifierInterface;

final class MailNotifier implements NotifierInterface
{
    public function __construct(
        private string $to = 'safety@example.com',
        private string $subject = 'New accident'
    ) {}

    public function notify(array $payload): void
    {
        $body = "New accident:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        @mail($this->to, $this->subject, $body);
    }
}

