#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);

$appFactory = new AppFactory($projectRoot);
$container = $appFactory->createContainer();

// Warm up services that register listeners with the event bus.
$container->getAccidentService();
$container->getProjectService();
$container->getHotspotService();
$container->getNotificationService();
$container->getCountermeasureService();
$container->getRoadNetworkService();

$eventBus = $container->getEventBus();
$eventBus->startConsuming();

