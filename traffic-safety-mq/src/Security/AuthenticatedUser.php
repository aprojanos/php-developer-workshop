<?php

declare(strict_types=1);

namespace App\Security;

use SharedKernel\Enum\UserRole;
use SharedKernel\Model\User;

final class AuthenticatedUser
{
    /**
     * @param array<string, mixed> $tokenClaims
     */
    public function __construct(
        private readonly User $user,
        private readonly array $tokenClaims
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTokenClaims(): array
    {
        return $this->tokenClaims;
    }

    public function getTokenId(): string
    {
        $tokenId = $this->tokenClaims['jti'] ?? null;
        if (!is_string($tokenId) || trim($tokenId) === '') {
            throw new \RuntimeException('Authenticated token is missing a JTI claim.');
        }

        return $tokenId;
    }

    public function getUserId(): int
    {
        if ($this->user->id === null) {
            throw new \RuntimeException('Authenticated user model is missing an identifier.');
        }

        return $this->user->id;
    }

    public function getRole(): UserRole
    {
        return $this->user->role;
    }

    public function hasRole(UserRole|string $role): bool
    {
        $expectedRole = $role instanceof UserRole ? $role->value : (string)$role;

        return strtolower($this->user->role->value) === strtolower($expectedRole);
    }

    /**
     * @param list<UserRole|string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}


