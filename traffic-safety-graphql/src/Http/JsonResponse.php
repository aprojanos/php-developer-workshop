<?php

declare(strict_types=1);

namespace App\Http;

use Throwable;

final class JsonResponse extends Response
{
    /**
     * @param mixed $data
     */
    public static function ok(mixed $data, int $status = 200): self
    {
        return new self(
            $status,
            [
                'Content-Type' => 'application/json',
            ],
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, int $status = 400, array $context = []): self
    {
        return self::ok(
            [
                'error' => [
                    'message' => $message,
                    'context' => $context,
                ],
            ],
            $status
        );
    }

    public static function fromThrowable(Throwable $throwable): self
    {
        $status = $throwable instanceof HttpException ? $throwable->getStatusCode() : 500;

        return self::ok(
            [
                'error' => [
                    'message' => $throwable->getMessage(),
                    'type' => $throwable::class,
                ],
            ],
            $status
        );
    }
}

