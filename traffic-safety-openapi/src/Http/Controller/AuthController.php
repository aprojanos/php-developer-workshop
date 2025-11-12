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
        }, false);
        $router->add('POST', '/api/auth/refresh', function (Request $request): Response {
            return $this->refresh($request);
        }, false);
        $router->add('POST', '/api/auth/logout', function (Request $request): Response {
            return $this->logout($request);
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

        $jwtManager = $this->container->getJwtManager();
        $refreshManager = $this->container->getRefreshTokenManager();
        $accessRegistry = $this->container->getAccessTokenRegistry();

        $tokenId = null;
        $tokenExpiresAt = null;
        $token = $jwtManager->issueToken($user, null, $tokenId, $tokenExpiresAt);
        if ($user->id === null) {
            throw new HttpException('User record is missing an identifier.', 500);
        }
        $accessRegistry->register($user->id, $tokenId, $tokenExpiresAt);

        $refreshToken = $refreshManager->issue($user);

        return $this->json([
            'token' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => (int)($_ENV['JWT_TTL'] ?? 3600),
            'refreshToken' => $refreshToken->token,
            'refreshTokenExpiresAt' => $refreshToken->expiresAt->format('c'),
            'user' => DomainSerializer::user($user),
        ]);
    }

    private function refresh(Request $request): Response
    {
        $refreshTokenValue = $this->requireJsonString($request, 'refreshToken');

        $refreshManager = $this->container->getRefreshTokenManager();
        $activeToken = $refreshManager->getActiveToken($refreshTokenValue);

        $userService = $this->container->getUserService();
        $user = $userService->findById($activeToken->userId);

        if ($user === null) {
            $refreshManager->revokeById($activeToken->id);
            throw new HttpException('User no longer exists.', 401);
        }

        if (!$user->isActive) {
            $refreshManager->revokeById($activeToken->id);
            throw new HttpException('User account is inactive.', 403);
        }

        if ($user->id === null) {
            $refreshManager->revokeById($activeToken->id);
            throw new HttpException('User record is missing an identifier.', 500);
        }

        $accessRegistry = $this->container->getAccessTokenRegistry();
        $jwtManager = $this->container->getJwtManager();

        $tokenId = null;
        $tokenExpiresAt = null;
        $jwtToken = $jwtManager->issueToken($user, null, $tokenId, $tokenExpiresAt);
        $accessRegistry->register($user->id, $tokenId, $tokenExpiresAt);

        $newRefreshToken = $refreshManager->rotateExisting($activeToken, $user);

        $userService->recordLogin($user->id, new DateTimeImmutable('now'));

        return $this->json([
            'token' => $jwtToken,
            'tokenType' => 'Bearer',
            'expiresIn' => (int)($_ENV['JWT_TTL'] ?? 3600),
            'refreshToken' => $newRefreshToken->token,
            'refreshTokenExpiresAt' => $newRefreshToken->expiresAt->format('c'),
            'user' => DomainSerializer::user($user),
        ]);
    }

    private function logout(Request $request): Response
    {
        $authUser = $this->requireAuthenticatedUser($request);
        $invalidateAll = (bool)$request->json('invalidateAll', false);

        $refreshManager = $this->container->getRefreshTokenManager();
        $accessRegistry = $this->container->getAccessTokenRegistry();

        if ($invalidateAll) {
            $refreshManager->revokeAllForUser($authUser->getUserId());
            $accessRegistry->revokeAllForUser($authUser->getUserId());
        } else {
            $refreshTokenValue = $this->requireJsonString($request, 'refreshToken');
            $refreshManager->revokeForUser($authUser->getUserId(), $refreshTokenValue);
        }

        try {
            $accessRegistry->revoke($authUser->getTokenId());
        } catch (\Throwable) {
            // Best effort; ignore if already revoked or absent.
        }

        return $this->noContent();
    }
}

