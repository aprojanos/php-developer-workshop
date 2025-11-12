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
use OpenApi\Annotations as OA;

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

    /**
     * @OA\Post(
     *     path="/api/users",
     *     operationId="registerUser",
     *     summary="Register a new user.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"email","password"},
     *             additionalProperties=true,
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created.",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=422, description="Invalid payload.")
     * )
     */
    private function registerUser(Request $request): Response
    {
        $payload = $request->getBody();
        $this->assertHasKey($payload, 'email');
        $this->assertHasKey($payload, 'password');

        $user = UserFactory::create($payload);
        $created = $this->container->getUserService()->register($user);

        return $this->created(DomainSerializer::user($created));
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     operationId="listUsers",
     *     summary="List users.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User collection.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/User")
     *             )
     *         )
     *     )
     * )
     */
    private function listUsers(): Response
    {
        $users = $this->container->getUserService()->all();

        return $this->json([
            'data' => DomainSerializer::users($users),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     operationId="getUser",
     *     summary="Get a user by id.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User details.",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=404, description="User not found.")
     * )
     */
    private function getUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $user = $this->container->getUserService()->findById($id);

        if ($user === null) {
            throw new HttpException('User not found.', 404);
        }

        return $this->json(DomainSerializer::user($user));
    }

    /**
     * @OA\Get(
     *     path="/api/users/by-email",
     *     operationId="getUserByEmail",
     *     summary="Find a user by email address.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="email")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User details.",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=404, description="User not found.")
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     operationId="updateUser",
     *     summary="Update user information.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", additionalProperties=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated.",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=404, description="User not found.")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/users/{id}/role",
     *     operationId="changeUserRole",
     *     summary="Change a user's role.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"role"},
     *             @OA\Property(property="role", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated.",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=422, description="Invalid role.")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/users/{id}/record-login",
     *     operationId="recordUserLogin",
     *     summary="Record a login timestamp for a user.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="loggedInAt", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=204, description="Login recorded.")
     * )
     */
    private function recordLogin(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $loggedInAt = $request->json('loggedInAt');
        $timestamp = $loggedInAt !== null ? new \DateTimeImmutable((string)$loggedInAt) : new \DateTimeImmutable('now');

        $this->container->getUserService()->recordLogin($id, $timestamp);

        return $this->noContent();
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/activate",
     *     operationId="activateUser",
     *     summary="Activate a user.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(response=204, description="User activated.")
     * )
     */
    private function activateUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getUserService()->activate($id);

        return $this->noContent();
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/deactivate",
     *     operationId="deactivateUser",
     *     summary="Deactivate a user.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(response=204, description="User deactivated.")
     * )
     */
    private function deactivateUser(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getUserService()->deactivate($id);

        return $this->noContent();
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     operationId="deleteUser",
     *     summary="Delete a user.",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(response=204, description="User deleted.")
     * )
     */
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

