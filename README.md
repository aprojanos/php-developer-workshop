PHP fejlesztői akadémia
=======================
Az alábbi gyakorlatokat fogjuk elvégezni a kurzuson. A repóban javaslatok találhatóak ezek lehetséges megoldásaita.
# PHP környezetek
A gyakorlatokhoz a PHP-CLI parancssori környezetre van szükség. Ha ezek nem találhatóak meg a saját gépünkön akkor ajánlott dockeres környezetben PHP konténert futtatni és ezen keresztül indítani a feladatokat. 
Ehhez tartozik egy `docker-compose.yml` fájl melyben különböző PHP verziókat találunk (PHP 8.2 - 8.5)
### Docker környezet indítása
```bash
docker-compose up -d
```
### Példa konténerben történő futtatásra
```sh
docker exec -it php-84 php traffic-safety/src/index.php
```
# Gyakorlatok
## PHP újdonságok
### Az Accident  osztály
Az Accident osztály közúti baleseteket modellez. A balesetek két csoportra oszthatóak: emberi sérülés is történt vagy csak anyagi kár keletkezett. Ha emberi sérülés is keletkezett akkor azt osztályozzuk a lesúlyosabb sérülés alapján: minor, moderate, severe, fatal. Ha csak anyagi kár volt, akkor meg kell adni a kár pénzbeli értékét.
Írjuk át az osztályt modern PHP elemek felhasználásával
Lehetőleg alkalmazzuk:
- typed properties 7.4
- backed enums 8.1
- constructor property promotion 8.0
- asszimetrikus láthaóság 8.4
- property hooks 8.4
```php
class Accident {

    protected $id;
    protected $type;
    protected $severity;
    protected $cost;

    public function __construct($id, $type = 'injury', $severity = 'minor', $cost = 0.0)
    {
        $this->id = $id;
        $this->type = $type;
        $this->severity = $severity;
        $this->cost = $cost;
    }

    public function getId()
    {
        return $this->id;
    }
    
    public function getType()
    {
        return $this->type;
    }
    
    public function getSeverity()
    {
        return $this->severity;
    }

    public function setSeverity($severity)
    {
        $this->severity = $severity;
    }

    public function getCost()
    {
        if ($this->cost > 0) {
            return $this->cost;
        } else {
            if ($this->type == "property_damage_only") {
                return 0;
            } else {
                $cost = 0;
                switch($this->severity) {
                    case 'minor':
                        $cost = 1000;
                        break;
                    case 'serious':
                        $cost = 10000;
                        break;
                    case 'severe':
                        $cost = 20000;
                        break;
                    case 'fatal':
                        $cost = 50000;
                        break;
                    default:
                        $cost = $this->cost;
                }
                return $cost;
            }
        }
    }
        
    public static function fromJson($json) {
        $obj = json_decode($json);
        if (json_last_error() === JSON_ERROR_NONE) {
            return new self($obj->id, $obj->type, $obj->severity, $obj->cost);
        }
        return null;
    }    
}
```
### Javasolt megoldás
`php-ujdonsagok/Accident.php`
## Tesztek
A `tests-exercise` mappában található projekthez írjunk teszteket.

Projektstruktúra:
```
tests-exercise/
├── src/
│   ├── Entity/
│   │   ├── Accident.php
│   │   ├── Intersection.php
│   │   └── RoadSegment.php
│   ├── Repository/
│   │   ├── AccidentRepository.php
│   │   └── IntersectionRepository.php
│   └── Service/
│       ├── SafetyAnalysisService.php
│       └── StatisticsService.php
├── tests/
│   ├── Entity/
│   ├── Repository/
│   ├── Service/
└── composer.json
```
1. Írjunk egységteszteket az `Accident` osztályhoz
2. Írjunk egységtesztet a `RoadSegment`  osztály `calculateRiskFactor` metódusához
3. Írjunk egységtesztet az `Intersection` osztály `calculateSafetyScore` metódusához
4. Írjunk tesztet az SafetyAnalysisService osztályhoz
   - `AccidentRepository`, `IntersectionRepository` osztályok használatával, tesztadatbázissal
   - `AccidentRepository`, `IntersectionRepository` osztályok helyett használjunk mock objektumot
### Javasolt megoldás
`tests-solution` mappában.

Futtatás:
```bash
cd tests-solution
composer install
vendor/bin/phpunit tests/
```
## Clean Code - osztály refaktorálása
Adott a NotificationManager osztály mely magába sűrít minden műveletet ami egy felhasználói értesítéshez szükséges. Refaktoráljuk a következő szempontok szerint:
- SRP egy osztálynak csak egy felelősségge legyen
- DIP / DI - az osztályok függőségeiket kívülről kapják, ne maguk hozzák létre
- Használjunk Interfészeket
- Lehetőleg alkalmazzunk OOP mintákat (Factory, Strategy, Repository)

`legacy/NotificationManager.php`
```php
<?php

class NotificationManager
{
    private $db_connection;

    // A konstruktor maga hozza létre a függőségét
    public function __construct()
    {
        // Adatbázis-kapcsolat szimulálása
        echo "Connecting to Legacy DB (mysql_connect)...<br>";
        // Valódi kódban itt pl. egy mysql_connect() lenne
        $this->db_connection = [
            1 => ['name' => 'Kiss János', 'email' => 'janos@example.com', 'phone' => '+36301234567'],
            2 => ['name' => 'Nagy Anna', 'email' => 'anna@example.com', 'phone' => '+36709876543'],
        ];
    }

    /**
     * Sérti az SRP-t
     * 1. Lekérdezi a felhasználót (adat réteg)
     * 2. Formázza az üzenetet (business logic)
     * 3. Eldönti, hogyan küldjön (control flow)
     * 4. Elküldi az üzenetet (küldési logika)
     */
    public function sendNotification($userId, $message, $type = 'email')
    {
        // 1. Felhasználó lekérdezése
        $user = null;
        if (isset($this->db_connection[$userId])) {
            $user = $this->db_connection[$userId];
        }

        if (!$user) {
            echo "User not found with ID: $userId<br>";
            return false;
        }

        // 2. Üzenet formázása (business logic)
        $formattedMessage = "Kedves " . $user['name'] . "! Üzenet: " . $message;

        // 3. Küldés típusa szerinti elágazás (sérülékeny, nem bővíthető)
        if ($type == 'email') {
            // 4. Email küldés logikája
            echo "Sending EMAIL to " . $user['email'] . ": " . $formattedMessage . "<br>";
            // mail($user['email'], 'Értesítés', $formattedMessage); // Szimulált küldés
            return true;

        } elseif ($type == 'sms') {
            // 4. SMS küldés logikája
            echo "Sending SMS to " . $user['phone'] . ": " . $formattedMessage . "<br>";
            // file_get_contents('https://api.sms-gateway.com/send?to='...); // Szimulált küldés
            return true;

        } else {
            echo "Unknown notification type: $type<br>";
            return false;
        }
    }

    public function __destruct()
    {
        echo "Disconnecting from Legacy DB...<br>";
        $this->db_connection = null;
    }
}
```

`legacy/index.php`
```php
<?php

require_once 'NotificationManager.php';

echo "Legacy Notification Sender" . PHP_EOL;

$manager = new NotificationManager();

$manager->sendNotification(1, "Ez egy teszt üzenet.", 'email');
$manager->sendNotification(2, "Ez egy másik teszt.", 'sms');
$manager->sendNotification(99, "Nem létező user.", 'email');
```

### Javasolt megoldás
`clean-code` mappában

Futtatás:
```sh
cd clean-code
php legacy/index.php
php refactored/index.php 
```
## Code Review
Végezzünk code-review-t az osztályon
### Szempontok
- Vannak-e prepared statementek minden dinamikus SQL-ben?
- Külön rétegek (DAL/Service/Controller), nincs echo a repository-ban?
- Jelszavak hash-elve vannak?
- Hibakezelés van (try/catch) és logolás metadata-szinten?
- Típusok és visszatérési értékek deklarálva?
- A konfiguráció (DB credentials) nincs kódba égetve?
- Teljesítményproblémák megfigyelhetőek-e?

```php
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
```
### Javasolt megoldás
`code-review` mappában

Futtatás:
```sh
cd code-review
composer install
vendor/bin/phpunit tests/
```
## Coding Standards & Design Patterns
### Feladat
Refaktoráljuk az alábbi alkalmazást a következő szempontok szerint:
- Tiszta kód elérése
  - PSR 1 / PSR 12 betartása
  - Tiszta függvények
- SOLID elvek betartása
  -  SRP - felelősségek szétválasztása (objektum létrehozás, mentés, mailküldés, logolás)
  - DIP/DI
- OOP tervezési minták alkalmazása
  - Factory: Accident
  - Template: ReportGenerator
  - Repository: Accident mentése memóriába, 
  - Adapter: Accident mentése PDO-val
  - Decorator - Accident Repository cache-eléssel

```php
<?php

class Accident {
    public $id;
    public $occurredAt; // string
    public $location;   // string
    public $severity;   // string: 'minor','serious','severe','fatal'
    public $type;       // 'PDO'|'Injury'
    public $cost;       // float
    public $roadSegmentId;
    public $intersectionId;
    public $countermeasure;
}

class LegacyAccidentManager {
    public function createFromArray(array $data) {
        $a = new Accident();
        $a->id = rand(1000,9999);
        $a->occurredAt = $data['occurredAt'] ?? date('c');
        $a->location = $data['location'] ?? 'unknown';
        $a->severity = $data['severity'] ?? 'minor'; // 'minor', 'serious', 'severe', 'fatal'
        $a->type = $data['type'] ?? 'PDO'; // 'PDO', 'Injury'
        $a->cost = $data['cost'] ?? 0;
        $a->roadSegmentId = $data['roadSegmentId'] ?? null;
        $a->intersectionId = $data['intersectionId'] ?? null;

        legacy_save_accident($a);
        
        $this->sendMail('safety@example.com', 'New accident', json_encode($data));
        
        $logFolder = __DIR__ . '/storage/logs/';
        @ mkdir($logFolder, 0755, true);
        file_put_contents($logFolder . 'log.txt', "Accident Created with ID: {$a->id}\n", FILE_APPEND);

        return $a;
    }

    public function reportAllToCsv() {
        
        $rows = legacy_get_all_accidents();
        
        $outFolder = __DIR__ . '/storage/export/';
        @ mkdir($outFolder, 0755, true);
        $outFile = $outFolder . 'accidents.csv';
        $out = fopen($outFile,'w');
        foreach ($rows as $r) {
            fputcsv($out, (array)$r);
        }
        fclose($out);

        return $outFile;
    }

    public function estimateTotalCost() {

        $rows = legacy_get_all_accidents();
        $sum = 0;
        foreach ($rows as $r) {
            $sum += $r->cost;
            if ($r->type == 'Injury') {
                $cost = 10000;
                switch($r->severity) {
                    case 'major':
                        $cost = 20000;
                        break;
                    case 'severe':
                        $cost = 40000;
                        break;
                    case 'fatal':
                        $cost = 100000;
                        break;
                }
                $sum += $cost;
            }
        }
        
        return $sum;
    }

    protected function sendMail($email, $subject, $body) {
        $mailFolder = __DIR__ . '/storage/mail/';
        @ mkdir($mailFolder, 0755, true);
        file_put_contents($mailFolder . 'sent.txt', "Mail sent to: {$email}, subject: {$subject}\n{$body}\n-----------------------------\n", FILE_APPEND);
    }
}

// CSV tároló
function legacy_save_accident($acc) {
    $line = implode(',', [
        $acc->id,
        $acc->occurredAt,
        str_replace(',', ';', $acc->location),
        $acc->severity,
        $acc->type,
        $acc->cost,
        $acc->roadSegmentId,
        $acc->intersectionId
    ]) . PHP_EOL;

    $dataFolder = __DIR__ . '/storage/data/';
    @ mkdir($dataFolder, 0755, true);
    file_put_contents($dataFolder . 'accidents.csv', $line, FILE_APPEND);
}

function legacy_get_all_accidents() {
    $f = __DIR__ . '/storage/data/accidents.csv';
    $rows = [];
    if (!file_exists($f)) return [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        [$id,$occurredAt,$location,$severity,$type,$cost,$roadSegmentId,$intersectionId] = explode(',', $line);
        $a = new Accident();
        $a->id = $id; $a->occurredAt = $occurredAt; $a->location = $location;
        $a->severity = $severity; $a->type = $type; $a->cost = (float)$cost;
        $a->roadSegmentId = $roadSegmentId; $a->intersectionId = $intersectionId;
        $rows[] = $a;
    }
    return $rows;
}

$manager = new LegacyAccidentManager();

$samples = [
    [
        'occurredAt' => '2025-10-28',
        'location' => 'Rákóczu út 3.',
        'severity' => 'minor',
        'type' => 'PDO',
        'cost' => 250.0,
        'roadSegmentId' => 10,
    ],
    [
        'occurredAt' => '2025-10-29',
        'location' => 'Zsolnay u. 12.',
        'severity' => 'minor',
        'type' => 'Injury',
        'cost' => 150.0,
        'roadSegmentId' => 3,
        'intersectionId' => 7,
    ],
    [
        'occurredAt' => '2025-10-31',
        'location' => 'Mártírok útja 30',
        'severity' => 'severe',
        'type' => 'Injury',
        'cost' => 400.0,
        'intersectionId' => 12,        
    ]
];

// tesztelés
echo "Creating sample accidents...\n";
foreach ($samples as $s) {
    $acc = $manager->createFromArray($s);
    echo "Created accident with id: {$acc->id}, type: {$acc->type}, cost: {$acc->cost}\n";
}

echo "\nEstimating total cost:\n";
echo $manager->estimateTotalCost() . "\n";

$csv = $manager->reportAllToCsv();
echo "Exported CSV to: {$csv}\n";

echo "Done.\n";
```
### Javasolt megoldás
`traffic-safety` mappában.

Futtatás:
```sh
cd traffic-safety
composer install
php legacy/index.php
php src/index.php
vendor/bin/phpunit tests/
```

