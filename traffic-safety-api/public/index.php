<?php

declare(strict_types=1);

use App\Bootstrap\AppFactory;
use App\Http\JsonResponse;
use App\Http\Router;

require_once __DIR__ . '/../vendor/autoload.php';

$appFactory = new AppFactory(__DIR__ . '/..');
$container = $appFactory->createContainer();
$router = new Router();

$appFactory->registerRoutes($router, $container);

try {
    $response = $router->dispatchFromGlobals($container);
} catch (Throwable $throwable) {
    $response = JsonResponse::fromThrowable($throwable);
}

$response->emit();

