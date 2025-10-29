<?php
declare(strict_types=1);

namespace Refactored\Repository;

use Refactored\Contract\UserRepositoryInterface;
use Refactored\Model\User;

// Egy "ál" repository, ami tömbből dolgozik a DB helyett.
// Könnyen lecserélhető egy valódi, pl. DoctrineUserRepository-ra.
class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $users = [];

    public function __construct()
    {
        $this->users[1] = new User(1, "Kiss János", "janos@example.com", "+36301234567");
        $this->users[2] = new User(2, "Nagy Anna", "anna@example.com", "+36709876543");
    }

    public function findById(int $userId): ?User
    {
        return $this->users[$userId] ?? null;
    }
}
