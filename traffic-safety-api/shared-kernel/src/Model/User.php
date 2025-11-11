<?php

namespace SharedKernel\Model;

use SharedKernel\Enum\UserRole;

final readonly class User
{
    public function __construct(
        public ?int $id,
        public string $email,
        public string $passwordHash,
        public ?string $firstName,
        public ?string $lastName,
        public UserRole $role,
        public bool $isActive,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $lastLoginAt = null,
    ) {}

    public function displayName(): string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);

        return $parts !== []
            ? implode(' ', $parts)
            : $this->email;
    }

    public function withId(?int $id): self
    {
        return new self(
            id: $id,
            email: $this->email,
            passwordHash: $this->passwordHash,
            firstName: $this->firstName,
            lastName: $this->lastName,
            role: $this->role,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }

    public function withPasswordHash(string $passwordHash): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            passwordHash: $passwordHash,
            firstName: $this->firstName,
            lastName: $this->lastName,
            role: $this->role,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }

    public function withRole(UserRole $role): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            passwordHash: $this->passwordHash,
            firstName: $this->firstName,
            lastName: $this->lastName,
            role: $role,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }

    public function withActivityStatus(bool $isActive): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            passwordHash: $this->passwordHash,
            firstName: $this->firstName,
            lastName: $this->lastName,
            role: $this->role,
            isActive: $isActive,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }

    public function withTimestamps(\DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            passwordHash: $this->passwordHash,
            firstName: $this->firstName,
            lastName: $this->lastName,
            role: $this->role,
            isActive: $this->isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }

    public function withUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            passwordHash: $this->passwordHash,
            firstName: $this->firstName,
            lastName: $this->lastName,
            role: $this->role,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }

    public function withLastLogin(?\DateTimeImmutable $lastLoginAt): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            passwordHash: $this->passwordHash,
            firstName: $this->firstName,
            lastName: $this->lastName,
            role: $this->role,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            lastLoginAt: $lastLoginAt,
        );
    }
}

