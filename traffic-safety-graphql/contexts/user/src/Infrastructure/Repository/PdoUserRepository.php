<?php

namespace UserContext\Infrastructure\Repository;

use SharedKernel\Contract\UserRepositoryInterface;
use SharedKernel\Model\User;
use UserContext\Domain\Factory\UserFactory;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function save(User $user): User
    {
        $now = new \DateTimeImmutable('now');

        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO users (
                email,
                password_hash,
                first_name,
                last_name,
                role,
                is_active,
                last_login_at,
                created_at,
                updated_at
            ) VALUES (
                :email,
                :password_hash,
                :first_name,
                :last_name,
                :role,
                :is_active,
                :last_login_at,
                :created_at,
                :updated_at
            )
            RETURNING *
        SQL);

        $stmt->execute([
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'role' => $user->role->value,
            'is_active' => $user->isActive,
            'last_login_at' => $user->lastLoginAt?->format('c'),
            'created_at' => $now->format('c'),
            'updated_at' => $now->format('c'),
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('Failed to insert user record.');
        }

        return UserFactory::create($row);
    }

    /**
     * @return User[]
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM users ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([UserFactory::class, 'create'], $rows);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? UserFactory::create($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? UserFactory::create($row) : null;
    }

    public function update(User $user): User
    {
        if ($user->id === null) {
            throw new \InvalidArgumentException('Cannot update a user without an identifier.');
        }

        $stmt = $this->pdo->prepare(<<<SQL
            UPDATE users
            SET email = :email,
                password_hash = :password_hash,
                first_name = :first_name,
                last_name = :last_name,
                role = :role,
                is_active = :is_active,
                last_login_at = :last_login_at,
                updated_at = :updated_at
            WHERE id = :id
            RETURNING *
        SQL);

        $updatedAt = new \DateTimeImmutable('now');

        $stmt->execute([
            'id' => $user->id,
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'role' => $user->role->value,
            'is_active' => $user->isActive,
            'last_login_at' => $user->lastLoginAt?->format('c'),
            'updated_at' => $updatedAt->format('c'),
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException("Failed to update user with ID {$user->id}.");
        }

        return UserFactory::create($row);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function recordLogin(int $id, \DateTimeImmutable $loggedInAt): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE users
            SET last_login_at = :last_login_at,
                updated_at = :updated_at
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $id,
            'last_login_at' => $loggedInAt->format('c'),
            'updated_at' => (new \DateTimeImmutable('now'))->format('c'),
        ]);
    }

    public function activate(int $id): void
    {
        $this->toggleActiveFlag($id, true);
    }

    public function deactivate(int $id): void
    {
        $this->toggleActiveFlag($id, false);
    }

    private function toggleActiveFlag(int $id, bool $isActive): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE users
            SET is_active = :is_active,
                updated_at = :updated_at
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $id,
            'is_active' => $isActive,
            'updated_at' => (new \DateTimeImmutable('now'))->format('c'),
        ]);
    }
}

