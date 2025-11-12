<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
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

    public function issueToken(User $user, ?int $ttlSeconds = null, ?string &$tokenId = null, ?DateTimeImmutable &$expiresAt = null): string
    {
        $issuedAt = time();
        $expiresTimestamp = $issuedAt + ($ttlSeconds ?? $this->defaultTtlSeconds);
        $tokenIdValue = bin2hex(random_bytes(32));

        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'traffic-safety-api',
            'sub' => $user->id,
            'iat' => $issuedAt,
            'exp' => $expiresTimestamp,
            'jti' => $tokenIdValue,
            'email' => $user->email,
            'role' => $user->role->value,
            'isActive' => $user->isActive,
        ];

        $tokenId = $tokenIdValue;
        $expiresAt = (new DateTimeImmutable())->setTimestamp($expiresTimestamp);

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

