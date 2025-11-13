<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use OpenApi\Generator;

final class OpenApiController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('GET', '/api/docs/openapi.json', fn(Request $request): Response => $this->openApiSpec(), false);
        $router->add('GET', '/swagger', fn(Request $request): Response => $this->swaggerUi(), false);
    }

    private function openApiSpec(): Response
    {
        $scanPaths = [
            dirname(__DIR__, 2),
        ];

        $openApi = Generator::scan($scanPaths);

        return new Response(
            200,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ],
            $openApi->toJson()
        );
    }

    private function swaggerUi(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Traffic Safety API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui-bundle.js"></script>
<script>
window.onload = function () {
    SwaggerUIBundle({
        url: '/api/docs/openapi.json',
        dom_id: '#swagger-ui',
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIBundle.SwaggerUIStandalonePreset
        ],
        layout: "BaseLayout"
    });
};
</script>
</body>
</html>
HTML;

        return new Response(
            200,
            [
                'Content-Type' => 'text/html; charset=utf-8',
            ],
            $html
        );
    }
}

