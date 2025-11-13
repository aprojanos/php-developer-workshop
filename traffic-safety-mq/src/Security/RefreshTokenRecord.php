<?php

declare(strict_types=1);

namespace App\Security;

final readonly class RefreshTokenRecord
{
    public function __construct(
        public int $id,
        public int $userId,
        public \DateTimeImmutable $expiresAt
    ) {}
}


