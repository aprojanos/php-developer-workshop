<?php

namespace App;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Simple User repository using PDO.
 * Responsibilities: csak DB műveletek — ne echo/log sensitive data.
 */
class SuggestedUserRepository
{
    public function __construct(private $pdo, private LoggerInterface $logger)
    {
        // explicit error mode beállítása ha a hívó nem tette meg
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Find user by id
     *
     * @param int $id
     * @return array|null associative array or null if not found
     * @throws InvalidArgumentException on invalid input
     * @throws RuntimeException on DB error
     */
    public function findUser(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid user id');
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user === false ? null : $user;
        } catch (PDOException $e) {
            $this->logger->error('DB error in findUser', ['exception' => $e]);
            throw new RuntimeException('Database error');
        }
    }

    /**
     * Create a new user
     *
     * @param array $data must contain 'name', 'email', 'password' (plain)
     * @return int inserted user id
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function createUser(array $data): int
    {
        // Basic validation (business validation can be richer and moved elsewhere)
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            throw new InvalidArgumentException('Missing required fields');
        }

        // Hash password before storing
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            $this->logger->error('Password hashing failed');
            throw new RuntimeException('Internal error');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (name, email, password) VALUES (:name, :email, :password)'
            );
            $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindValue(':password', $passwordHash, PDO::PARAM_STR);
            $stmt->execute();

            $id = (int)$this->pdo->lastInsertId();
            // Log only metadata (no sensitive user data)
            $this->logger->info('User created', ['user_id' => $id, 'email' => $data['email']]);
            return $id;
        } catch (PDOException $e) {
            $this->logger->error('DB error in createUser', ['exception' => $e]);
            throw new RuntimeException('Database error');
        }
    }

    /**
     * Get all users with posts
     *
     * @return array list of associative arrays
     */
    public function getAllUsersWithPosts(): array
    {
        $this->logger->info('Fetching all users with posts');
        // Egyetlen SQL lekérdezés JOIN-nal
        $sql = "
            SELECT u.id AS user_id, u.name, u.email, u.created_at,
                   p.id AS post_id, p.title, p.content, p.created_at AS post_created
            FROM users u
            LEFT JOIN posts p ON u.id = p.user_id
            ORDER BY u.id
        ";
    
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logger->debug('Rows fetched: ' . count($rows));

        $result = [];
        foreach ($rows as $row) {
            $uid = $row['user_id'];
            if (!isset($result[$uid])) {
                $result[$uid] = [
                    'id' => $uid,
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'created_at' => $row['created_at'],
                    'posts' => [],
                ];
            }
    
            if (!empty($row['post_id'])) {
                $result[$uid]['posts'][] = [
                    'id' => $row['post_id'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'created_at' => $row['post_created'],
                ];
            }
        }

        $this->logger->info('Returning ' . count($result) . ' users with posts');

        return array_values($result);
    }


    /**
     * Delete user by id
     *
     * @param int $id
     * @return bool true if deleted (rowCount > 0)
     */
    public function deleteUser(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid user id');
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $deleted = $stmt->rowCount() > 0;
            $this->logger->info('User delete attempted', ['user_id' => $id, 'deleted' => $deleted]);
            return $deleted;
        } catch (PDOException $e) {
            $this->logger->error('DB error in deleteUser', ['exception' => $e]);
            throw new RuntimeException('Database error');
        }
    }
}
