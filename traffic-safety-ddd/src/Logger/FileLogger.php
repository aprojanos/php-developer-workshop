<?php
namespace App\Logger;

use SharedKernel\Contract\LoggerInterface;

final class FileLogger implements LoggerInterface
{
    public function __construct(private string $path) {
        @mkdir(dirname($this->path), 0755, true);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context = []): void
    {
        $time = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $line = sprintf("[%s] %s: %s %s\n", $time, $level, $message, $this->contextToString($context));
        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }

    private function contextToString(array $context): string
    {
        if (empty($context)) {
            return '';
        }
        return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
