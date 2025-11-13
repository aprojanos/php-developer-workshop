<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Container;
use App\Http\Router;
use Dotenv\Dotenv;

final class AppFactory
{
    public function __construct(
        private readonly string $projectRoot
    ) {}

    public function createContainer(): Container
    {
        $this->bootstrapEnvironment();

        $container = new Container($this->projectRoot);
        $container->boot();

        return $container;
    }

    /**
     * @param Router $router
     * @param Container $container
     */
    public function registerRoutes(Router $router, Container $container): void
    {
        $router->register($container);
    }

    private function bootstrapEnvironment(): void
    {
        $envPath = $this->projectRoot . '/.env';
        if (!file_exists($envPath)) {
            return;
        }

        $dotenv = Dotenv::createImmutable($this->projectRoot);
        $dotenv->load();
    }
}

