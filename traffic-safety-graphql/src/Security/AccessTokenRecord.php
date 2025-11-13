<?php

declare(strict_types=1);

namespace App\Security;

final readonly class AccessTokenRecord
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $tokenId,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $revokedAt = null
    ) {}

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(\DateTimeImmutable $now = new \DateTimeImmutable('now')): bool
    {
        return $this->expiresAt <= $now;
    }
}


