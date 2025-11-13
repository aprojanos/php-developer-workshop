<?php

namespace UserContext\Domain\Factory;

use SharedKernel\Enum\UserRole;
use SharedKernel\Model\User;

final class UserFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): User
    {
        $passwordHash = $data['passwordHash']
            ?? $data['password_hash']
            ?? null;

        if ($passwordHash === null && isset($data['password'])) {
            $passwordHash = password_hash((string)$data['password'], PASSWORD_DEFAULT);
        }

        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new \InvalidArgumentException('UserFactory requires a password hash or plain password.');
        }

        $createdAt = self::toDateTimeImmutable($data['createdAt'] ?? $data['created_at'] ?? null);
        $updatedAt = self::toDateTimeImmutable($data['updatedAt'] ?? $data['updated_at'] ?? null);
        $lastLoginAt = self::toDateTimeImmutable($data['lastLoginAt'] ?? $data['last_login_at'] ?? null);

        $firstName = $data['firstName'] ?? $data['first_name'] ?? null;
        $lastName = $data['lastName'] ?? $data['last_name'] ?? null;

        $now = new \DateTimeImmutable('now');

        return new User(
            id: isset($data['id']) ? (int)$data['id'] : null,
            email: (string)($data['email'] ?? throw new \InvalidArgumentException('UserFactory requires an email.')),
            passwordHash: $passwordHash,
            firstName: $firstName !== null ? (string)$firstName : null,
            lastName: $lastName !== null ? (string)$lastName : null,
            role: isset($data['role']) ? UserRole::from((string)$data['role']) : UserRole::ANALYST,
            isActive: isset($data['isActive']) ? (bool)$data['isActive'] : (isset($data['is_active']) ? (bool)$data['is_active'] : true),
            createdAt: $createdAt ?? $now,
            updatedAt: $updatedAt ?? $now,
            lastLoginAt: $lastLoginAt,
        );
    }

    private static function toDateTimeImmutable(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new \DateTimeImmutable($value);
        }

        if (is_int($value)) {
            return (new \DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        return null;
    }
}

