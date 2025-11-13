<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\GraphQL\Accident\AccidentSchemaFactory;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;

final class AccidentGraphqlController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add(
            'POST',
            '/graphql/accidents',
            fn(Request $request): Response => $this->handle($request),
            true,
            self::ROLE_VIEW
        );
    }

    private function handle(Request $request): Response
    {
        $payload = $request->getBody();
        $query = $payload['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            throw new HttpException('GraphQL query must be a non-empty string.', 400);
        }

        $variables = $payload['variables'] ?? [];
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            if (!is_array($decoded)) {
                throw new HttpException('GraphQL variables must be a JSON object.', 400);
            }
            $variables = $decoded;
        } elseif ($variables === null) {
            $variables = [];
        } elseif (!is_array($variables)) {
            throw new HttpException('GraphQL variables must be provided as an object.', 400);
        }

        $operationName = $payload['operationName'] ?? null;
        if ($operationName !== null && !is_string($operationName)) {
            throw new HttpException('GraphQL operationName must be a string when provided.', 400);
        }
        $operationName = $operationName ?: null;

        $authenticatedUser = $this->requireAuthenticatedUser($request, self::ROLE_VIEW);

        $authResolver = function (?array $roles = null) use ($request, $authenticatedUser) {
            $rolesToCheck = $roles ?? self::ROLE_VIEW;

            if ($authenticatedUser->hasAnyRole($rolesToCheck)) {
                return $authenticatedUser;
            }

            return $this->requireAuthenticatedUser($request, $rolesToCheck);
        };

        $schema = (new AccidentSchemaFactory($this->container, $authResolver))->createSchema();

        $debug = $this->isDebug()
            ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE
            : DebugFlag::NONE;

        $result = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            [
                'request' => $request,
                'user' => $authenticatedUser,
            ],
            $variables,
            $operationName
        );

        $output = $result->toArray($debug);

        return $this->json($output);
    }

    private function isDebug(): bool
    {
        $env = $_ENV['APP_ENV'] ?? 'production';

        return in_array(strtolower($env), ['local', 'dev', 'development', 'test'], true);
    }
}


