<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\HttpException;
use DateTimeImmutable;
use PDO;
use PDOException;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Model\User;

final class RefreshTokenManager
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
        private readonly int $defaultTtlSeconds = 1209600 // 14 days
    ) {}

    public function issue(User $user, ?int $ttlSeconds = null): RefreshToken
    {
        if ($user->id === null) {
            throw new \InvalidArgumentException('Cannot issue a refresh token for a user without an identifier.');
        }

        $token = $this->generateToken();
        $hash = $this->hashToken($token);
        $expiresAt = $this->calculateExpiry($ttlSeconds);

        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO user_refresh_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        SQL);

        try {
            $statement->execute([
                'user_id' => $user->id,
                'token_hash' => $hash,
                'expires_at' => $expiresAt->format('c'),
            ]);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Failed to persist refresh token.', 0, $exception);
        }

        $this->logger->info('Refresh token issued', [
            'userId' => $user->id,
            'expiresAt' => $expiresAt->format('c'),
        ]);

        return new RefreshToken($token, $expiresAt);
    }

    public function getActiveToken(string $token): RefreshTokenRecord
    {
        $hash = $this->hashToken($token);
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, user_id, expires_at, revoked_at
            FROM user_refresh_tokens
            WHERE token_hash = :token_hash
            LIMIT 1
        SQL);
        $statement->execute(['token_hash' => $hash]);

        /** @var array{id:int,user_id:int,expires_at:string,revoked_at:?string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new HttpException('Refresh token is invalid.', 401);
        }

        if ($row['revoked_at'] !== null) {
            throw new HttpException('Refresh token has been revoked.', 401);
        }

        try {
            $expiresAt = new DateTimeImmutable($row['expires_at']);
        } catch (\Exception $exception) {
            $this->revokeById($row['id']);
            throw new \RuntimeException('Stored refresh token expiry could not be parsed.', 0, $exception);
        }

        $now = new DateTimeImmutable('now');
        if ($expiresAt <= $now) {
            $this->revokeById($row['id']);
            throw new HttpException('Refresh token has expired.', 401);
        }

        return new RefreshTokenRecord(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            expiresAt: $expiresAt
        );
    }

    public function revokeById(int $tokenId): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE user_refresh_tokens
            SET revoked_at = NOW()
            WHERE id = :id AND revoked_at IS NULL
        SQL);
        $statement->execute(['id' => $tokenId]);

        if ($statement->rowCount() > 0) {
            $this->logger->info('Refresh token revoked', ['tokenId' => $tokenId]);
        }
    }

    public function revokeForUser(int $userId, string $token): bool
    {
        $hash = $this->hashToken($token);
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE user_refresh_tokens
            SET revoked_at = NOW()
            WHERE token_hash = :token_hash
              AND user_id = :user_id
              AND revoked_at IS NULL
        SQL);

        $statement->execute([
            'token_hash' => $hash,
            'user_id' => $userId,
        ]);

        $revoked = $statement->rowCount() > 0;
        if ($revoked) {
            $this->logger->info('Refresh token revoked for user', [
                'userId' => $userId,
            ]);
        }

        return $revoked;
    }

    public function revokeAllForUser(int $userId): int
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE user_refresh_tokens
            SET revoked_at = NOW()
            WHERE user_id = :user_id
              AND revoked_at IS NULL
        SQL);
        $statement->execute(['user_id' => $userId]);

        $revokedCount = (int)$statement->rowCount();
        if ($revokedCount > 0) {
            $this->logger->info('All refresh tokens revoked for user', [
                'userId' => $userId,
                'revokedCount' => $revokedCount,
            ]);
        }

        return $revokedCount;
    }

    public function rotate(string $currentToken, User $user, ?int $ttlSeconds = null): RefreshToken
    {
        $record = $this->getActiveToken($currentToken);

        return $this->rotateExisting($record, $user, $ttlSeconds);
    }

    public function rotateExisting(RefreshTokenRecord $record, User $user, ?int $ttlSeconds = null): RefreshToken
    {
        if ($record->userId !== $user->id) {
            $this->revokeById($record->id);
            throw new HttpException('Refresh token does not belong to the authenticated user.', 401);
        }

        $this->revokeById($record->id);

        return $this->issue($user, $ttlSeconds);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(64));
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function calculateExpiry(?int $ttlSeconds): DateTimeImmutable
    {
        $ttl = $ttlSeconds ?? $this->defaultTtlSeconds;
        if ($ttl <= 0) {
            throw new \InvalidArgumentException('Refresh token TTL must be greater than zero.');
        }

        return (new DateTimeImmutable('now'))->modify(sprintf('+%d seconds', $ttl));
    }
}


