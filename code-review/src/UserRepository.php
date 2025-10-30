<?php

namespace App;

use PDO;
use Exception;

class UserRepository
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('mysql:host=localhost;dbname=test', 'root', 'root');
    }

    public function findUser($id)
    {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE id = $id");
        return $stmt->fetch();
    }


    public function createUser($data)
    {
        try {
            $sql = "INSERT INTO users (name, email, password)
                    VALUES ('{$data['name']}', '{$data['email']}', '{$data['password']}')";
            $this->pdo->exec($sql);
        } catch(Exception $e) {
            echo "User creation failed " . json_encode($data);
        }

        echo "User created successfully";
    }

    public function getAllUsersWithPosts()
    {
        $stmt = $this->pdo->query("SELECT * FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $userId = $user['id'];
            $posts = $this->pdo
                ->query("SELECT * FROM posts WHERE user_id = $userId")
                ->fetchAll(PDO::FETCH_ASSOC);
            $user['posts'] = $posts;
        }

        return $users;
    }
    public function deleteUser($id)
    {
        $this->pdo->exec("DELETE FROM users WHERE id = $id");
    }
}
