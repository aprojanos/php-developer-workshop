<?php
declare(strict_types=1);

// --- Egyszerű PSR-4 Autoloader (Composer helyett) ---
spl_autoload_register(function ($class) {
    $prefix = 'Refactored\\';
    $base_dir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
// --- Autoloader vége ---

// Névteret használunk
use Refactored\Repository\InMemoryUserRepository;
use Refactored\Factory\NotificationSenderFactory;
use Refactored\Service\NotificationService;
use Refactored\Model\NotificationType;

echo "Refactored Notification Sender" . PHP_EOL;

// --- "Dependency Injection Container" Setup ---
// Itt állítjuk össze a függőségeket (Dependency Injection)
// Ahelyett, hogy az osztályok maguk példányosítanának,
// mi adjuk át nekik a szükséges objektumokat.

$userRepository = new InMemoryUserRepository();
$senderFactory = new NotificationSenderFactory();

// "Injektáljuk" a függőségeket a service-be
$notificationService = new NotificationService(
    $userRepository,
    $senderFactory
);

// --- Alkalmazás Logika ---

// Típusbiztos hívás az Enum-mal
$notificationService->sendNotification(1, "Ez egy refaktorált üzenet.", NotificationType::Email);

$notificationService->sendNotification(2, "Ez egy SMS a másik usernek.", NotificationType::Sms);

$notificationService->sendNotification(99, "Nem létező user.", NotificationType::Email);

