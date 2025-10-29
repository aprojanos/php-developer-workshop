<?php

require_once 'NotificationManager.php';

echo "Legacy Notification Sender" . PHP_EOL;

$manager = new NotificationManager();

$manager->sendNotification(1, "Ez egy teszt üzenet.", 'email');
$manager->sendNotification(2, "Ez egy másik teszt.", 'sms');
$manager->sendNotification(99, "Nem létező user.", 'email');
