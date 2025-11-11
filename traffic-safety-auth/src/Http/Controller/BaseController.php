<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Container;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\Response;

abstract class BaseController implements ControllerInterface
{
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
}

