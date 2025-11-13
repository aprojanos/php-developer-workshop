<?php

namespace UserContext\Application;

use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\UserRepositoryInterface;
use SharedKernel\Enum\UserRole;
use SharedKernel\Model\User;

final class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private ?LoggerInterface $logger = null,
    ) {}

    public function register(User $user): User
    {
        $created = $this->repository->save($user);
        $this->logger?->info('User registered', $this->userContext($created));

        return $created;
    }

    /**
     * @return User[]
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    public function findById(int $id): ?User
    {
        $user = $this->repository->findById($id);
        if ($user !== null) {
            $this->logger?->info('User retrieved', $this->userContext($user));
        }

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        $user = $this->repository->findByEmail($email);
        if ($user !== null) {
            $this->logger?->info('User retrieved by email', array_merge(
                $this->userContext($user),
                ['email' => $email]
            ));
        }

        return $user;
    }

    public function update(User $user): User
    {
        if ($user->id === null) {
            throw new \InvalidArgumentException('Cannot update a user without an identifier.');
        }

        $existing = $this->repository->findById($user->id);
        if ($existing === null) {
            throw new \InvalidArgumentException("User with ID {$user->id} not found.");
        }

        $updated = $this->repository->update(
            $user->withUpdatedAt(new \DateTimeImmutable('now'))
        );

        $this->logger?->info('User updated', $this->userContext($updated));

        return $updated;
    }

    public function changeRole(int $userId, UserRole $role): User
    {
        $user = $this->requireUser($userId);

        if ($user->role === $role) {
            return $user;
        }

        $updated = $this->repository->update(
            $user
                ->withRole($role)
                ->withUpdatedAt(new \DateTimeImmutable('now'))
        );

        $this->logger?->info('User role changed', array_merge(
            $this->userContext($updated),
            ['previousRole' => $user->role->value]
        ));

        return $updated;
    }

    public function recordLogin(int $userId, \DateTimeImmutable $loggedInAt): void
    {
        $this->repository->recordLogin($userId, $loggedInAt);
        $this->logger?->info('User login recorded', [
            'userId' => $userId,
            'loggedInAt' => $loggedInAt->format('c'),
        ]);
    }

    public function activate(int $userId): void
    {
        $user = $this->requireUser($userId);
        if ($user->isActive) {
            return;
        }

        $this->repository->activate($userId);
        $this->logger?->info('User activated', $this->userContext($user));
    }

    public function deactivate(int $userId): void
    {
        $user = $this->requireUser($userId);
        if (!$user->isActive) {
            return;
        }

        $this->repository->deactivate($userId);
        $this->logger?->info('User deactivated', $this->userContext($user));
    }

    public function delete(int $userId): void
    {
        $user = $this->requireUser($userId);
        $this->repository->delete($userId);
        $this->logger?->info('User deleted', $this->userContext($user));
    }

    private function requireUser(int $userId): User
    {
        $user = $this->repository->findById($userId);
        if ($user === null) {
            throw new \InvalidArgumentException("User with ID {$userId} not found.");
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function userContext(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role->value,
            'isActive' => $user->isActive,
            'lastLoginAt' => $user->lastLoginAt?->format('c'),
            'createdAt' => $user->createdAt->format('c'),
            'updatedAt' => $user->updatedAt->format('c'),
        ];
    }
}

