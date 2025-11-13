<?php

namespace UserContext\Infrastructure\Seeder;

use SharedKernel\Enum\UserRole;

final class UserSeeder
{
    private int $nextId;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->nextId = $this->fetchMaxId();
    }

    public function run(int $count = 5, bool $purgeExisting = true, bool $includeAdmin = true): void
    {
        if ($purgeExisting) {
            $this->purge();
            $this->nextId = 0;
        }

        if ($includeAdmin) {
            $this->insertUser($this->generateUserPayload(
                email: 'admin@dmsone.hu',
                firstName: 'System',
                lastName: 'Admin',
                role: UserRole::ADMIN,
                password: 'DmsOne123!'
            ));
            $count = max(0, $count - 1);
        }

        for ($i = 0; $i < $count; $i++) {
            $this->insertUser($this->generateRandomUserPayload($i));
        }
    }

    public function purge(): void
    {
        $this->pdo->exec('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
    }

    /**
     * @return array<string, mixed>
     */
    private function generateRandomUserPayload(int $index): array
    {
        $roles = UserRole::cases();
        $role = $roles[random_int(0, count($roles) - 1)];

        $firstNames = ['Péter', 'Sándor', 'Dániel', 'Csaba', 'Gergő', 'Zoltán', 'János'];
        $lastNames = ['Farkas', 'Mihály', 'Nagy', 'Nikolausz', 'Schwarcz', 'Tolnai', 'Vass', 'Apró'];

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];

        $email = sprintf(
            '%s.%s%d@dmsone.hu',
            $this->normalizeForEmail($firstName),
            $this->normalizeForEmail($lastName) ?: 'user',
            $index + 1
        );

        return $this->generateUserPayload(
            email: $email,
            firstName: $firstName,
            lastName: $lastName,
            role: $role,
            // password: sprintf('%s%s%d!', substr($firstName, 0, 2), substr($lastName, 0, 2), random_int(10, 99))
            password: 'DmsOne123!'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function generateUserPayload(
        string $email,
        string $firstName,
        string $lastName,
        UserRole $role,
        string $password
    ): array {
        $now = new \DateTimeImmutable('now');

        return [
            'id' => $this->generateId(),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role->value,
            'is_active' => true,
            'created_at' => $now->format('c'),
            'updated_at' => $now->format('c'),
            'last_login_at' => null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertUser(array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (
                id,
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
                :id,
                :email,
                :password_hash,
                :first_name,
                :last_name,
                :role,
                :is_active,
                :last_login_at,
                :created_at,
                :updated_at
            )'
        );

        $stmt->execute([
            'id' => $payload['id'],
            'email' => $payload['email'],
            'password_hash' => $payload['password_hash'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'role' => $payload['role'],
            'is_active' => $payload['is_active'],
            'last_login_at' => $payload['last_login_at'],
            'created_at' => $payload['created_at'],
            'updated_at' => $payload['updated_at'],
        ]);
    }

    private function fetchMaxId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM users');
        $result = $stmt !== false ? $stmt->fetchColumn() : 0;

        return (int)$result;
    }

    private function generateId(): int
    {
        $this->nextId++;

        return $this->nextId;
    }

    private function normalizeForEmail(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = $transliterated !== false ? $transliterated : $value;
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

        return $normalized ?? '';
    }
}

