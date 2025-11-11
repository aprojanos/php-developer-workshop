<?php

declare(strict_types=1);

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SharedKernel\Model\User;

final class JwtManager
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm = 'HS256',
        private readonly int $defaultTtlSeconds = 3600
    ) {}

    public function issueToken(User $user, ?int $ttlSeconds = null): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + ($ttlSeconds ?? $this->defaultTtlSeconds);

        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'traffic-safety-api',
            'sub' => $user->id,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'email' => $user->email,
            'role' => $user->role->value,
            'isActive' => $user->isActive,
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

        /** @var array<string, mixed> $payload */
        $payload = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        return $payload;
    }
}

