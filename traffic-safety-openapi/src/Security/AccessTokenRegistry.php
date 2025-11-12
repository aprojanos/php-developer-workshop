<?php

declare(strict_types=1);

namespace App\Security;

use PDO;
use PDOException;
use SharedKernel\Contract\LoggerInterface;

final class AccessTokenRegistry
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger
    ) {}

    public function register(int $userId, string $tokenId, \DateTimeImmutable $expiresAt): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO user_access_tokens (user_id, token_id, issued_at, expires_at)
            VALUES (:user_id, :token_id, NOW(), :expires_at)
            ON CONFLICT (token_id) DO UPDATE
                SET user_id = EXCLUDED.user_id,
                    issued_at = NOW(),
                    expires_at = EXCLUDED.expires_at,
                    revoked_at = NULL
        SQL);

        try {
            $statement->execute([
                'user_id' => $userId,
                'token_id' => $tokenId,
                'expires_at' => $expiresAt->format('c'),
            ]);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Unable to register access token.', 0, $exception);
        }

        $this->logger->info('Access token registered', [
            'userId' => $userId,
            'tokenId' => $tokenId,
            'expiresAt' => $expiresAt->format('c'),
        ]);
    }

    public function find(string $tokenId): ?AccessTokenRecord
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, user_id, token_id, expires_at, revoked_at
            FROM user_access_tokens
            WHERE token_id = :token_id
            LIMIT 1
        SQL);
        $statement->execute(['token_id' => $tokenId]);

        /** @var array{id:int,user_id:int,token_id:string,expires_at:string,revoked_at:?string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new AccessTokenRecord(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            tokenId: (string)$row['token_id'],
            expiresAt: new \DateTimeImmutable($row['expires_at']),
            revokedAt: $row['revoked_at'] !== null ? new \DateTimeImmutable($row['revoked_at']) : null
        );
    }

    public function revoke(string $tokenId): bool
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE user_access_tokens
            SET revoked_at = NOW()
            WHERE token_id = :token_id
              AND revoked_at IS NULL
        SQL);
        $statement->execute(['token_id' => $tokenId]);

        $revoked = $statement->rowCount() > 0;
        if ($revoked) {
            $this->logger->info('Access token revoked', ['tokenId' => $tokenId]);
        }

        return $revoked;
    }

    public function revokeAllForUser(int $userId): int
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE user_access_tokens
            SET revoked_at = NOW()
            WHERE user_id = :user_id
              AND revoked_at IS NULL
        SQL);
        $statement->execute(['user_id' => $userId]);

        $revoked = (int)$statement->rowCount();
        if ($revoked > 0) {
            $this->logger->info('Access tokens revoked for user', [
                'userId' => $userId,
                'revokedCount' => $revoked,
            ]);
        }

        return $revoked;
    }

    public function pruneExpired(): int
    {
        $statement = $this->pdo->query(<<<SQL
            DELETE FROM user_access_tokens
            WHERE expires_at <= NOW()
        SQL);

        return $statement !== false ? (int)$statement->rowCount() : 0;
    }
}


