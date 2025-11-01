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
use Refactored\Service\UserService;
use Refactored\Model\NotificationType;

echo "Refactored Notification Sender" . PHP_EOL;

// --- "Dependency Injection Container" Setup ---
// Itt állítjuk össze a függőségeket (Dependency Injection)
// Ahelyett, hogy az osztályok maguk példányosítanának,
// mi adjuk át nekik a szükséges objektumokat.

$userRepository = new InMemoryUserRepository();
$senderFactory = new NotificationSenderFactory();

// "Injektáljuk" a függőségeket a service-be
$notificationService = new NotificationService($senderFactory);


// --- Alkalmazás Logika ---

// Típusbiztos hívás az Enum-mal
$userService = new UserService($userRepository);

$user = $userService->findById(1);
$notificationService->sendNotification($user, "Ez egy refaktorált üzenet.", NotificationType::Email);
$user = $userService->findById(2);
$notificationService->sendNotification($user, "Ez egy SMS a másik usernek.", NotificationType::Sms);

