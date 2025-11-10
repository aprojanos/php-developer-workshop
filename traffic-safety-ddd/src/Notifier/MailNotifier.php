<?php
namespace App\Notifier;

use SharedKernel\Contract\NotifierInterface;

final class MailNotifier implements NotifierInterface
{
    public function __construct(
        private string $to = 'safety@example.com',
        private string $subject = 'New accident'
    ) {}

    public function notify(array $payload): void
    {
        // Egyszerűsített: adatok JSON-ként a levél törzsébe
        $body = "New accident:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        // @ jel a mail hívásnál, hogy dev környezetben ne dobjon hibát, ha nincs MTA
        @mail($this->to, $this->subject, $body);
    }
}
