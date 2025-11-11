<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Container;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\Response;
use App\Security\AuthGuard;
use App\Security\AuthenticatedUser;

abstract class BaseController implements ControllerInterface
{
    protected const ROLE_VIEW = ['viewer', 'analyst', 'manager', 'admin'];
    protected const ROLE_ANALYST = ['analyst', 'manager', 'admin'];
    protected const ROLE_MANAGER = ['manager', 'admin'];
    protected const ROLE_ADMIN = ['admin'];

    public function __construct(
        protected readonly Container $container
    ) {}

    protected function json(mixed $data, int $status = 200): Response
    {
        return JsonResponse::ok($data, $status);
    }

    protected function created(mixed $data): Response
    {
        return $this->json($data, 201);
    }

    protected function noContent(): Response
    {
        return new Response(204, [], '');
    }

    protected function requireRouteParam(Request $request, string $name): string
    {
        $value = $request->getRouteParam($name);
        if ($value === null) {
            throw new HttpException(sprintf('Missing route parameter: %s', $name), 400);
        }

        return $value;
    }

    protected function requireJsonString(Request $request, string $key): string
    {
        $value = $request->json($key);
        if (!is_string($value) || trim($value) === '') {
            throw new HttpException(sprintf('Field "%s" is required and must be a string.', $key), 422);
        }

        return $value;
    }

    protected function requireJsonInt(Request $request, string $key): int
    {
        $value = $request->json($key);
        if (!is_numeric($value)) {
            throw new HttpException(sprintf('Field "%s" is required and must be numeric.', $key), 422);
        }

        return (int)$value;
    }

    protected function requireJsonArray(Request $request, string $key): array
    {
        $value = $request->json($key);
        if (!is_array($value)) {
            throw new HttpException(sprintf('Field "%s" must be an array.', $key), 422);
        }

        return $value;
    }

    /**
     * @param list<string>|null $allowedRoles
     */
    protected function requireAuthenticatedUser(Request $request, ?array $allowedRoles = null): AuthenticatedUser
    {
        $authUser = $request->getAttribute(AuthGuard::ATTRIBUTE_AUTH_USER);
        if (!$authUser instanceof AuthenticatedUser) {
            // Fallback safeguard: attempt to authenticate now.
            $request = AuthGuard::ensureAuthenticated($request, $this->container, $allowedRoles);
            $authUser = $request->getAttribute(AuthGuard::ATTRIBUTE_AUTH_USER);
        }

        if (!$authUser instanceof AuthenticatedUser) {
            throw new HttpException('Authentication is required.', 401);
        }

        if ($allowedRoles !== null && !$authUser->hasAnyRole($allowedRoles)) {
            throw new HttpException('You are not allowed to access this resource.', 403);
        }

        return $authUser;
    }
}

