<?php

declare(strict_types=1);

namespace App\Http\Controller;

use AccidentContext\Domain\Factory\AccidentFactory;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use SharedKernel\DTO\AccidentSearchDTO;
use OpenApi\Attributes as OA;

final class AccidentController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('GET', '/api/accidents', fn(Request $request): Response => $this->listAccidents(), true, self::ROLE_VIEW);
        $router->add('GET', '/api/accidents/total-estimated-cost', fn(Request $request): Response => $this->totalEstimatedCost(), true, self::ROLE_VIEW);
        $router->add('GET', '/api/accidents/{id}', fn(Request $request): Response => $this->getAccident($request), true, self::ROLE_VIEW);
        $router->add('POST', '/api/accidents', fn(Request $request): Response => $this->createAccident($request), true, self::ROLE_ANALYST);
        $router->add('PUT', '/api/accidents/{id}', fn(Request $request): Response => $this->updateAccident($request), true, self::ROLE_ANALYST);
        $router->add('DELETE', '/api/accidents/{id}', fn(Request $request): Response => $this->deleteAccident($request), true, self::ROLE_MANAGER);
        $router->add('POST', '/api/accidents/calculate-total-cost', fn(Request $request): Response => $this->calculateTotalCost($request), true, self::ROLE_ANALYST);
        $router->add('POST', '/api/accidents/search', fn(Request $request): Response => $this->searchAccidents($request), true, self::ROLE_VIEW);
    }

    #[OA\Get(
        path: '/api/accidents',
        operationId: 'listAccidents',
        summary: 'List all recorded accidents.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Collection of accidents.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Accident')
                        )
                    ]
                )
            )
        ]
    )]
    private function listAccidents(): Response
    {
        $accidents = $this->container->getAccidentService()->all();

        return $this->json([
            'data' => DomainSerializer::accidents($accidents),
        ]);
    }

    #[OA\Get(
        path: '/api/accidents/{id}',
        operationId: 'getAccident',
        summary: 'Retrieve a single accident by its identifier.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Accident identifier',
                schema: new OA\Schema(type: 'integer', format: 'int64')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Accident found.',
                content: new OA\JsonContent(ref: '#/components/schemas/Accident')
            ),
            new OA\Response(response: 404, description: 'Accident not found.')
        ]
    )]
    private function getAccident(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $accident = $this->container->getAccidentService()->findById($id);

        if ($accident === null) {
            throw new HttpException('Accident not found.', 404);
        }

        return $this->json(DomainSerializer::accident($accident));
    }

    #[OA\Post(
        path: '/api/accidents',
        operationId: 'createAccident',
        summary: 'Create a new accident record.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                description: 'Accident payload as accepted by the AccidentFactory.',
                additionalProperties: true,
                properties: [
                    new OA\Property(property: 'id', type: 'integer', format: 'int64')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Accident created.',
                content: new OA\JsonContent(ref: '#/components/schemas/Accident')
            ),
            new OA\Response(response: 422, description: 'Validation error.')
        ]
    )]
    private function createAccident(Request $request): Response
    {
        $payload = $request->getBody();
        if (!isset($payload['id'])) {
            throw new HttpException('Field "id" is required.', 422);
        }

        $accident = AccidentFactory::create($payload);
        $service = $this->container->getAccidentService();
        $service->create($accident);

        $created = $service->findById($accident->id);

        return $this->created($created !== null ? DomainSerializer::accident($created) : [
            'id' => $accident->id,
            'status' => 'created',
        ]);
    }

    #[OA\Put(
        path: '/api/accidents/{id}',
        operationId: 'updateAccident',
        summary: 'Update an existing accident record.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                additionalProperties: true,
                properties: [
                    new OA\Property(property: 'id', type: 'integer', format: 'int64', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Accident updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/Accident')
            ),
            new OA\Response(response: 404, description: 'Accident not found.')
        ]
    )]
    private function updateAccident(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $payload = $request->getBody();
        $payload['id'] = $id;

        $accident = AccidentFactory::create($payload);

        $service = $this->container->getAccidentService();
        $service->update($accident);

        $updated = $service->findById($id);

        return $this->json($updated !== null ? DomainSerializer::accident($updated) : ['id' => $id]);
    }

    #[OA\Delete(
        path: '/api/accidents/{id}',
        operationId: 'deleteAccident',
        summary: 'Delete an accident.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Accident deleted.')
        ]
    )]
    private function deleteAccident(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getAccidentService()->delete($id);

        return $this->noContent();
    }

    #[OA\Get(
        path: '/api/accidents/total-estimated-cost',
        operationId: 'getTotalEstimatedAccidentCost',
        summary: 'Get the total estimated cost for all accidents.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Aggregate cost.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'totalEstimatedCost', type: 'number', format: 'double')
                    ]
                )
            )
        ]
    )]
    private function totalEstimatedCost(): Response
    {
        $total = $this->container->getAccidentService()->totalEstimatedCost();

        return $this->json(['totalEstimatedCost' => $total]);
    }

    #[OA\Post(
        path: '/api/accidents/calculate-total-cost',
        operationId: 'calculateAccidentTotalCost',
        summary: 'Calculate the combined cost for provided accidents payload.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['accidents'],
                properties: [
                    new OA\Property(
                        property: 'accidents',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            additionalProperties: true
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Total cost calculated.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'totalCost', type: 'number', format: 'double')
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Invalid payload.')
        ]
    )]
    private function calculateTotalCost(Request $request): Response
    {
        $data = $this->requireJsonArray($request, 'accidents');
        $accidents = array_map(static fn(array $item) => AccidentFactory::create($item), $data);

        $total = $this->container->getAccidentService()->calculateTotalCost($accidents);

        return $this->json(['totalCost' => $total]);
    }

    #[OA\Post(
        path: '/api/accidents/search',
        operationId: 'searchAccidents',
        summary: 'Search accidents using filters.',
        tags: ['Accidents'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                description: 'Search filters as defined by AccidentSearchDTO.'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Search results.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Accident')
                        ),
                        new OA\Property(property: 'count', type: 'integer')
                    ]
                )
            )
        ]
    )]
    private function searchAccidents(Request $request): Response
    {
        $payload = $request->getBody();
        $searchDto = AccidentSearchDTO::fromArray($payload);

        $results = $this->container->getAccidentService()->search($searchDto);

        return $this->json([
            'data' => DomainSerializer::accidents($results),
            'count' => count($results),
        ]);
    }
}

