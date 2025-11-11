<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $body
     * @param array<string, string> $routeParams
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $headers,
        private readonly array $queryParams,
        private readonly array $body,
        private array $routeParams = []
    ) {}

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getRouteParam(string $name, ?string $default = null): ?string
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function withRouteParams(array $routeParams): self
    {
        $clone = clone $this;
        $clone->routeParams = $routeParams;

        return $clone;
    }

    public function json(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = (string)$value;
            }
        }

        $rawBody = file_get_contents('php://input');
        $decodedBody = [];
        if ($rawBody !== false && $rawBody !== '') {
            $decodedBody = json_decode($rawBody, true);
            if (!is_array($decodedBody)) {
                $decodedBody = [];
            }
        }

        return new self(
            method: $method,
            path: $path,
            headers: $headers,
            queryParams: $_GET ?? [],
            body: $decodedBody
        );
    }
}

