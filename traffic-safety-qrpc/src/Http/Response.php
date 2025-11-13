<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body
    ) {}

    public function emit(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}

