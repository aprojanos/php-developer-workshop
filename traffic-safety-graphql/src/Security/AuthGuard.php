<?php

declare(strict_types=1);

namespace App\Security;

use App\Container;
use App\Http\HttpException;
use App\Http\Request;
use Firebase\JWT\ExpiredException;
use SharedKernel\Contract\LoggerInterface;

final class AuthGuard
{
    public const ATTRIBUTE_AUTH_USER = 'auth.user';
    public const ATTRIBUTE_TOKEN_CLAIMS = 'auth.token.claims';

    /**
     * @param list<string>|null $allowedRoles
     */
    public static function ensureAuthenticated(Request $request, Container $container, ?array $allowedRoles = null): Request
    {
        $existing = $request->getAttribute(self::ATTRIBUTE_AUTH_USER);
        if ($existing instanceof AuthenticatedUser) {
            if ($allowedRoles !== null && !$existing->hasAnyRole($allowedRoles)) {
                self::logDenied($container->getLogger(), $request, $existing->getUser()->id ?? 0, 'Insufficient role.');
                throw new HttpException('You are not allowed to access this resource.', 403);
            }

            return $request;
        }

        $logger = $container->getLogger();
        $authorization = $request->getHeader('Authorization');
        if ($authorization === null || trim($authorization) === '') {
            self::logDenied($logger, $request, null, 'Missing Authorization header.');
            throw new HttpException('Missing Authorization header.', 401);
        }

        if (!preg_match('/^Bearer\\s+(?<token>.+)$/i', $authorization, $matches)) {
            self::logDenied($logger, $request, null, 'Authorization header must use Bearer scheme.');
            throw new HttpException('Authorization header must use the Bearer scheme.', 401);
        }

        $token = trim($matches['token']);
        if ($token === '') {
            self::logDenied($logger, $request, null, 'Authorization token is empty.');
            throw new HttpException('Authorization token is empty.', 401);
        }

        try {
            $claims = $container->getJwtManager()->decode($token);
        } catch (ExpiredException $exception) {
            self::logDenied($logger, $request, null, 'Token has expired.');
            throw new HttpException('Token has expired.', 401, $exception);
        } catch (\Throwable $throwable) {
            self::logDenied($logger, $request, null, 'Token could not be decoded.');
            throw new HttpException('Invalid token.', 401, $throwable);
        }

        $userId = $claims['sub'] ?? null;
        if (!is_numeric($userId)) {
            self::logDenied($logger, $request, null, 'Token subject claim is missing or invalid.');
            throw new HttpException('Token subject (sub) claim is missing or invalid.', 401);
        }

        $registry = $container->getAccessTokenRegistry();
        $tokenId = $claims['jti'] ?? null;
        if (!is_string($tokenId) || trim($tokenId) === '') {
            self::logDenied($logger, $request, (int)$userId, 'Token ID (jti) claim is missing.');
            throw new HttpException('Token identifier is missing.', 401);
        }

        $record = $registry->find($tokenId);
        if ($record === null) {
            self::logDenied($logger, $request, (int)$userId, 'Access token is not registered.');
            throw new HttpException('Access token is invalid.', 401);
        }

        if ($record->isRevoked()) {
            self::logDenied($logger, $request, (int)$userId, 'Access token has been revoked.');
            throw new HttpException('Access token has been revoked.', 401);
        }

        if ($record->isExpired()) {
            $registry->revoke($tokenId);
            self::logDenied($logger, $request, (int)$userId, 'Access token is expired.');
            throw new HttpException('Access token has expired.', 401);
        }

        $user = $container->getUserService()->findById((int)$userId);
        if ($user === null) {
            self::logDenied($logger, $request, (int)$userId, 'User from token no longer exists.');
            throw new HttpException('User no longer exists.', 401);
        }

        if (!$user->isActive) {
            self::logDenied($logger, $request, $user->id, 'User account is inactive.');
            throw new HttpException('User account is inactive.', 403);
        }

        $authenticatedUser = new AuthenticatedUser($user, $claims);

        if ($allowedRoles !== null && !$authenticatedUser->hasAnyRole($allowedRoles)) {
            self::logDenied($logger, $request, $user->id, 'User lacks required roles.', $allowedRoles);
            throw new HttpException('You are not allowed to access this resource.', 403);
        }

        if ($user->id !== $record->userId) {
            $registry->revoke($tokenId);
            self::logDenied($logger, $request, $user->id, 'Access token does not belong to user.');
            throw new HttpException('Token is not associated with this user.', 401);
        }

        self::logGranted($logger, $request, $user->id, $user->role->value);

        return $request
            ->withAttribute(self::ATTRIBUTE_AUTH_USER, $authenticatedUser)
            ->withAttribute(self::ATTRIBUTE_TOKEN_CLAIMS, $claims);
    }

    private static function logDenied(LoggerInterface $logger, Request $request, ?int $userId, string $reason, ?array $requiredRoles = null): void
    {
        $logger->error('Request denied', array_filter([
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'userId' => $userId,
            'reason' => $reason,
            'requiredRoles' => $requiredRoles,
        ], static fn(mixed $value): bool => $value !== null && $value !== []));
    }

    private static function logGranted(LoggerInterface $logger, Request $request, int $userId, string $role): void
    {
        $logger->info('Request granted', [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'userId' => $userId,
            'role' => $role,
        ]);
    }
}


