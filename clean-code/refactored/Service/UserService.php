<?php
declare(strict_types=1);

namespace Refactored\Service;

use Refactored\Contract\UserRepositoryInterface;
use Refactored\Model\User;

// Service, ami a User adatok kezelését végzi.
// Csak absztrakcióktól függ (DIP - Dependency Inversion Principle).
class UserService
{
    // PHP 8.1: readonly
    // PHP 8.0: Constructor Property Promotion
    // A függőségeket "injektáljuk" (Dependency Injection).
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Felhasználó lekérése ID alapján.
     *
     * @param int $userId A felhasználó azonosítója
     * @return User|null A felhasználó, vagy null ha nem található
     */
    public function findById(int $userId): ?User
    {
        return $this->userRepository->findById($userId);
    }
}
