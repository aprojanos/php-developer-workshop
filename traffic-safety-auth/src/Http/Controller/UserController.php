<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use SharedKernel\Enum\UserRole;
use UserContext\Domain\Factory\UserFactory;

final class UserController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('POST', '/api/users', fn(Request $request): Response => $this->registerUser($request), true, self::ROLE_ADMIN);
        $router->add('GET', '/api/users', fn(Request $request): Response => $this->listUsers(), true, self::ROLE_ADMIN);
        $router->add('GET', '/api/users/{id}', fn(Request $request): Response => $this->getUser($request), true, self::ROLE_ADMIN);
        $router->add('GET', '/api/users/by-email', fn(Request $request): Response => $this->getUserByEmail($request), true, self::ROLE_ADMIN);
        $router->add('PUT', '/api/users/{id}', fn(Request $request): Response => $this->updateUser($request), true, self::ROLE_ADMIN);
        $router->add('POST', '/api/users/{id}/role', fn(Request $request): Response => $this->changeRole($request), true, self::ROLE_ADMIN);
        $router->add('POST', '/api/users/{id}/record-login', fn(Request $request): Response => $this->recordLogin($request), true, self::ROLE_ADMIN);
        $router->add('POST', '/api/users/{id}/activate', fn(Request $request): Response => $this->activateUser($request), true, self::ROLE_ADMIN);
        $router->add('POST', '/api/users/{id}/deactivate', fn(Request $request): Response => $this->deactivateUser($request), true, self::ROLE_ADMIN);
        $router->add('DELETE', '/api/users/{id}', fn(Request $request): Response => $this->deleteUser($request), true, self::ROLE_ADMIN);
    }

    private function registerUser(Request $request): Response
    {
        $payload = $request->getBody();
        $this->assertHasKey($payload, 'email');
        $this->assertHasKey($payload, 'password');

        $user = UserFactory::create($payload);
        $created = $this->container->getUserService()->register($user);

        return $this->created(DomainSerializer::user($created));
    }

    private function listUsers(): Response
    {
        $users = $this->container->getUserService()->all();

        return $this->json([
            'data' => DomainSerializer::users($users),
        ]);
    }

    private function getUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $user = $this->container->getUserService()->findById($id);

        if ($user === null) {
            throw new HttpException('User not found.', 404);
        }

        return $this->json(DomainSerializer::user($user));
    }

    private function getUserByEmail(Request $request): Response
    {
        $email = $request->query('email');
        if (!is_string($email) || trim($email) === '') {
            throw new HttpException('Query parameter "email" is required.', 422);
        }

        $user = $this->container->getUserService()->findByEmail($email);
        if ($user === null) {
            throw new HttpException('User not found.', 404);
        }

        return $this->json(DomainSerializer::user($user));
    }

    private function updateUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $service = $this->container->getUserService();

        $existing = $service->findById($id);
        if ($existing === null) {
            throw new HttpException('User not found.', 404);
        }

        $payload = $request->getBody();
        $payload['id'] = $id;
        $payload['email'] = $payload['email'] ?? $existing->email;
        $payload['passwordHash'] = $payload['passwordHash'] ?? ($payload['password'] ?? null ? null : $existing->passwordHash);
        $payload['firstName'] = $payload['firstName'] ?? $existing->firstName;
        $payload['lastName'] = $payload['lastName'] ?? $existing->lastName;
        $payload['role'] = $payload['role'] ?? $existing->role->value;
        $payload['isActive'] = $payload['isActive'] ?? $existing->isActive;
        $payload['createdAt'] = $existing->createdAt->format('c');
        $payload['updatedAt'] = $existing->updatedAt->format('c');
        if ($existing->lastLoginAt !== null) {
            $payload['lastLoginAt'] = $existing->lastLoginAt->format('c');
        }

        $user = UserFactory::create($payload);
        $updated = $service->update($user);

        return $this->json(DomainSerializer::user($updated));
    }

    private function changeRole(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $roleValue = $this->requireJsonString($request, 'role');

        try {
            $role = UserRole::from($roleValue);
        } catch (\ValueError $error) {
            throw new HttpException(sprintf('Invalid role "%s".', $roleValue), 422, $error);
        }

        $updated = $this->container->getUserService()->changeRole($id, $role);

        return $this->json(DomainSerializer::user($updated));
    }

    private function recordLogin(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $loggedInAt = $request->json('loggedInAt');
        $timestamp = $loggedInAt !== null ? new \DateTimeImmutable((string)$loggedInAt) : new \DateTimeImmutable('now');

        $this->container->getUserService()->recordLogin($id, $timestamp);

        return $this->noContent();
    }

    private function activateUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getUserService()->activate($id);

        return $this->noContent();
    }

    private function deactivateUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getUserService()->deactivate($id);

        return $this->noContent();
    }

    private function deleteUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getUserService()->delete($id);

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertHasKey(array $payload, string $key): void
    {
        if (!array_key_exists($key, $payload)) {
            throw new HttpException(sprintf('Field "%s" is required.', $key), 422);
        }
    }
}

