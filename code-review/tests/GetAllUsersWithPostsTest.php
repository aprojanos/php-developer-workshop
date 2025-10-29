<?php

use PHPUnit\Framework\TestCase;
use App\SuggestedUserRepository;
use Components\ConsoleLogger;

/**
 * Egyszerű PDO proxy, delegálja a valódit és kegészíti a lekérdezéshívások számlálásával 
 */
class ProxyPdo
{
    private int $queryCount = 0;

    public function __construct(private PDO $pdo) { }

    public function query(string $sql)
    {
        $this->queryCount++;
        return $this->pdo->query($sql);
    }

    public function prepare(string $sql)
    {
        return $this->pdo->prepare($sql);
    }

    public function exec(string $sql)
    {
        return $this->pdo->exec($sql);
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    // minden egyéb hívás továbbítása a PDO-nak
    public function __call($name, $arguments)
    {
        return $this->pdo->$name(...$arguments);
    }
}


final class GetAllUsersWithPostsTest extends TestCase
{
    private ProxyPdo $pdoProxy;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                password TEXT NOT NULL,
                created_at TEXT
            );
        ');

        $pdo->exec('
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT,
                created_at TEXT
            );
        ');

        // Seed: 3 user, posztokkal
        $pdo->exec("INSERT INTO users (name, email, password, created_at)
            VALUES ('Alice','a@example.com','x', datetime())");
        $pdo->exec("INSERT INTO users (name, email, password, created_at)
            VALUES ('Bob','b@example.com','x', datetime())");
        $pdo->exec("INSERT INTO users (name, email, password, created_at)
            VALUES ('Carol','c@example.com','x', datetime())");

        // posztok: Alice 1, Bob 2, Carol 0
        $pdo->exec("INSERT INTO posts (user_id, title, content, created_at)
            VALUES (1,'A1','..', datetime())");
        $pdo->exec("INSERT INTO posts (user_id, title, content, created_at)
            VALUES (2,'B1','..', datetime())");
        $pdo->exec("INSERT INTO posts (user_id, title, content, created_at)
            VALUES (2,'B2','..', datetime())");

        // PDO wrapper kiegészítve query() hivások számlálásával
        $this->pdoProxy = new ProxyPdo($pdo);
        
    }

    public function testRepositoryUsesSingleQuery(): void
    {
        $consoleLogger = new ConsoleLogger();

        $repo = new SuggestedUserRepository($this->pdoProxy, $consoleLogger);
        $result = $repo->getAllUsersWithPosts();
        
        $this->assertCount(3, $result, '3 felhasználót ad vissza');
        $this->assertCount(1, $result[0]['posts']);
        $this->assertCount(2, $result[1]['posts']);
        $this->assertCount(0, $result[2]['posts']);

        $this->assertEquals(1, $this->pdoProxy->getQueryCount(), 'Csak egy lekérdezés kell (JOIN)');

        // ellenőrizzűk hogy milyen szintű logolás hányszor volt
        $this->assertEquals(2, $consoleLogger->getLogCount('INFO'));
        $this->assertEquals(1, $consoleLogger->getLogCount('DEBUG'));
    }
}
