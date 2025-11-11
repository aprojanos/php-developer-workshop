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

final class Router
{
    /**
     * @var array<string, list<RouteDefinition>>
     */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $method = strtoupper($method);
        $this->routes[$method] ??= [];
        $this->routes[$method][] = $this->compileRoute($pattern, $handler);
    }

    public function register(Container $container): void
    {
        $controllers = [
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

            return ($route->handler)($requestWithParams, $container);
        }

        throw new HttpException(sprintf('No route matched %s %s', $method, $path), 404);
    }

    private function compileRoute(string $pattern, callable $handler): RouteDefinition
    {
        $variableNames = [];
        $regex = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $matches) use (&$variableNames): string {
            $variableNames[] = $matches[1];
            return '([a-zA-Z0-9\-_]+)';
        }, $pattern) ?? $pattern;

        $regex = '#^' . $regex . '$#';

        return new RouteDefinition(
            regex: $regex,
            variables: $variableNames,
            handler: Closure::fromCallable($handler)
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
     */
    public function __construct(
        public readonly string $regex,
        public readonly array $variables,
        public readonly Closure $handler
    ) {}
}

