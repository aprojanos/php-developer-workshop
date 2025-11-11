<?php

declare(strict_types=1);

namespace App\Http;

use App\Container;
use App\Http\Controller\AccidentController;
use App\Http\Controller\CountermeasureController;
use App\Http\Controller\HotspotController;
use App\Http\Controller\ProjectController;
use App\Http\Controller\RoadNetworkController;
use Closure;
use App\Http\Controller\UserController;
use App\Http\Controller\AuthController;

final class Router
{
    /**
     * @var array<string, list<RouteDefinition>>
     */
    private array $routes = [];

    /**
     * @param callable(Request, Container): Response $handler
     * @param list<string>|null $allowedRoles
     */
    public function add(string $method, string $pattern, callable $handler, bool $requiresAuth = true, ?array $allowedRoles = null): void
    {
        $method = strtoupper($method);
        $this->routes[$method] ??= [];
        $this->routes[$method][] = $this->compileRoute($pattern, $handler, $requiresAuth, $allowedRoles);
    }

    public function register(Container $container): void
    {
        $controllers = [
            new AuthController($container),
            new UserController($container),
            new AccidentController($container),
            new ProjectController($container),
            new CountermeasureController($container),
            new HotspotController($container),
            new RoadNetworkController($container),
        ];

        foreach ($controllers as $controller) {
            $controller->register($this);
        }
    }

    public function dispatchFromGlobals(Container $container): Response
    {
        $request = Request::fromGlobals();

        return $this->dispatch($request, $container);
    }

    public function dispatch(Request $request, Container $container): Response
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getPath();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route->regex, $path, $matches) !== 1) {
                continue;
            }

            $routeParams = [];
            foreach ($route->variables as $index => $name) {
                $routeParams[$name] = $matches[$index + 1] ?? null;
            }

            $requestWithParams = $request->withRouteParams($routeParams);

            if ($route->requiresAuth) {
                $requestWithParams = \App\Security\AuthGuard::ensureAuthenticated(
                    $requestWithParams,
                    $container,
                    $route->allowedRoles
                );
            }

            return ($route->handler)($requestWithParams, $container);
        }

        throw new HttpException(sprintf('No route matched %s %s', $method, $path), 404);
    }

    /**
     * @param callable(Request, Container): Response $handler
     * @param list<string>|null $allowedRoles
     */
    private function compileRoute(string $pattern, callable $handler, bool $requiresAuth, ?array $allowedRoles): RouteDefinition
    {
        $variableNames = [];
        $regex = preg_replace_callback('/\{(\w+)\}/', static function (array $matches) use (&$variableNames): string {
            $variableNames[] = $matches[1];
            return '(\w+)';
        }, $pattern) ?? $pattern;

        $regex = '#^' . $regex . '$#';

        return new RouteDefinition(
            regex: $regex,
            variables: $variableNames,
            handler: Closure::fromCallable($handler),
            requiresAuth: $requiresAuth,
            allowedRoles: $allowedRoles !== null ? array_values(array_unique(array_map('strtolower', $allowedRoles))) : null
        );
    }
}

/**
 * @internal
 */
final class RouteDefinition
{
    /**
     * @param list<string> $variables
     * @param callable(Request, Container): Response $handler
     * @param callable(Request, Container): Response $handler
     * @param list<string>|null $allowedRoles
     */
    public function __construct(
        public readonly string $regex,
        public readonly array $variables,
        public readonly Closure $handler,
        public readonly bool $requiresAuth,
        public readonly ?array $allowedRoles
    ) {}
}

