<?php
namespace Components;
// Simple console logger implementing the minimal LoggerInterface.
use Psr\Log\LoggerInterface;

class ConsoleLogger implements LoggerInterface
{
    private $counter = [];
    public function emergency(string|\Stringable $message, array $context = []): void { $this->log('EMERGENCY', $message, $context); }
    public function alert(string|\Stringable $message, array $context = []): void     { $this->log('ALERT', $message, $context); }
    public function critical(string|\Stringable $message, array $context = []): void  { $this->log('CRITICAL', $message, $context); }
    public function error(string|\Stringable $message, array $context = []): void     { $this->log('ERROR', $message, $context); }
    public function warning(string|\Stringable $message, array $context = []): void   { $this->log('WARNING', $message, $context); }
    public function notice(string|\Stringable $message, array $context = []): void    { $this->log('NOTICE', $message, $context); }
    public function info(string|\Stringable $message, array $context = []): void      { $this->log('INFO', $message, $context); }
    public function debug(string|\Stringable $message, array $context = []): void     { $this->log('DEBUG', $message, $context); }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        if (!isset($this->counter[$level])) {
            $this->counter[$level] = 0;
        }
        $this->counter[$level]++;
        $time = date('Y-m-d H:i:s');
        echo "[$time][$level] $message" . PHP_EOL;
    }

    public function getLogCount(mixed $level)
    {
        if (isset($this->counter[$level])) {
            return $this->counter[$level];
        }
        return 0;
    }
}
