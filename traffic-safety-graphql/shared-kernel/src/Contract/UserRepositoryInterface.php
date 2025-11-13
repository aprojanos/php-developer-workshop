<?php

namespace SharedKernel\Contract;

use SharedKernel\Model\User;

interface UserRepositoryInterface
{
    public function save(User $user): User;

    /** @return User[] */
    public function all(): array;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function update(User $user): User;

    public function delete(int $id): void;

    public function recordLogin(int $id, \DateTimeImmutable $loggedInAt): void;

    public function activate(int $id): void;

    public function deactivate(int $id): void;
}

