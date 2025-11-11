<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use App\Http\Response;
use DateTimeImmutable;

final class AuthController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('POST', '/api/auth/login', function (Request $request): Response {
            return $this->login($request);
        });
    }

    private function login(Request $request): Response
    {
        $email = strtolower(trim($this->requireJsonString($request, 'email')));
        $password = $this->requireJsonString($request, 'password');

        $userService = $this->container->getUserService();
        $user = $userService->findByEmail($email);

        if ($user === null || !password_verify($password, $user->passwordHash)) {
            throw new HttpException('Invalid credentials.', 401);
        }

        if ($user->id === null) {
            throw new HttpException('User record is missing an identifier.', 500);
        }

        if (!$user->isActive) {
            throw new HttpException('User account is inactive.', 403);
        }

        $userService->recordLogin($user->id, new DateTimeImmutable('now'));

        $token = $this->container->getJwtManager()->issueToken($user);

        return $this->json([
            'token' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => (int)($_ENV['JWT_TTL'] ?? 3600),
            'user' => DomainSerializer::user($user),
        ]);
    }
}

