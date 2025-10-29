<?php

class NotificationManager
{
    private $db_connection;

    // A konstruktor maga hozza létre a függőségét
    public function __construct()
    {
        // Adatbázis-kapcsolat szimulálása
        echo "Connecting to Legacy DB (mysql_connect)..." . PHP_EOL;
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
            echo "User not found with ID: $userId" . PHP_EOL;
            return false;
        }

        // 2. Üzenet formázása (business logic)
        $formattedMessage = "Kedves " . $user['name'] . "! Üzenet: " . $message;

        // 3. Küldés típusa szerinti elágazás (sérülékeny, nem bővíthető)
        if ($type == 'email') {
            // 4. Email küldés logikája
            echo "Sending EMAIL to " . $user['email'] . ": " . $formattedMessage . PHP_EOL;
            // mail($user['email'], 'Értesítés', $formattedMessage); // Szimulált küldés
            return true;

        } elseif ($type == 'sms') {
            // 4. SMS küldés logikája
            echo "Sending SMS to " . $user['phone'] . ": " . $formattedMessage . PHP_EOL;
            // file_get_contents('https://api.sms-gateway.com/send?to='...); // Szimulált küldés
            return true;

        } else {
            echo "Unknown notification type: $type"  . PHP_EOL;
            return false;
        }
    }

    public function __destruct()
    {
        echo "Disconnecting from Legacy DB..."  . PHP_EOL;
        $this->db_connection = null;
    }
}
