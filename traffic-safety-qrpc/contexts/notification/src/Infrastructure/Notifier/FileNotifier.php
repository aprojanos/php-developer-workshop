<?php

namespace NotificationContext\Infrastructure\Notifier;

use SharedKernel\Contract\NotifierInterface;

final class FileNotifier implements NotifierInterface
{
    public function __construct(private string $path)
    {
        @mkdir(dirname($this->path), 0755, true);
    }

    public function notify(array $payload): void
    {
        $time = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $entry = [
            'time' => $time,
            'payload' => $payload,
        ];
        file_put_contents(
            $this->path,
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

