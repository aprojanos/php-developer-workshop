<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use SharedKernel\Enum\FunctionalClass;
use SharedKernel\Enum\IntersectionControlType;
use SharedKernel\Model\Intersection;
use SharedKernel\Model\RoadSegment;
use SharedKernel\ValueObject\GeoLocation;
use OpenApi\Annotations as OA;

final class RoadNetworkController extends BaseController
{
    public function register(Router $router): void
    {
        // Intersections
        $router->add('GET', '/api/road-network/intersections', fn(Request $request): Response => $this->listIntersections(), true, self::ROLE_VIEW);
        $router->add('GET', '/api/road-network/intersections/{id}', fn(Request $request): Response => $this->getIntersection($request), true, self::ROLE_VIEW);
        $router->add('POST', '/api/road-network/intersections', fn(Request $request): Response => $this->createIntersection($request), true, self::ROLE_MANAGER);
        $router->add('PUT', '/api/road-network/intersections/{id}', fn(Request $request): Response => $this->updateIntersection($request), true, self::ROLE_MANAGER);
        $router->add('DELETE', '/api/road-network/intersections/{id}', fn(Request $request): Response => $this->deleteIntersection($request), true, self::ROLE_MANAGER);

        // Road segments
        $router->add('GET', '/api/road-network/segments', fn(Request $request): Response => $this->listRoadSegments(), true, self::ROLE_VIEW);
        $router->add('GET', '/api/road-network/segments/{id}', fn(Request $request): Response => $this->getRoadSegment($request), true, self::ROLE_VIEW);
        $router->add('POST', '/api/road-network/segments', fn(Request $request): Response => $this->createRoadSegment($request), true, self::ROLE_MANAGER);
        $router->add('PUT', '/api/road-network/segments/{id}', fn(Request $request): Response => $this->updateRoadSegment($request), true, self::ROLE_MANAGER);
        $router->add('DELETE', '/api/road-network/segments/{id}', fn(Request $request): Response => $this->deleteRoadSegment($request), true, self::ROLE_MANAGER);
    }

    /**
     * @OA\Get(
     *     path="/api/road-network/intersections",
     *     operationId="listIntersections",
     *     summary="List intersections.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Intersection collection.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Intersection")
     *             )
     *         )
     *     )
     * )
     */
    private function listIntersections(): Response
    {
        $intersections = $this->container->getRoadNetworkService()->listIntersections();

        return $this->json([
            'data' => DomainSerializer::intersections($intersections),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/road-network/intersections/{id}",
     *     operationId="getIntersection",
     *     summary="Get an intersection by id.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Intersection details.",
     *         @OA\JsonContent(ref="#/components/schemas/Intersection")
     *     ),
     *     @OA\Response(response=404, description="Intersection not found.")
     * )
     */
    private function getIntersection(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $intersection = $this->container->getRoadNetworkService()->getIntersection($id);
        if ($intersection === null) {
            throw new HttpException('Intersection not found.', 404);
        }

        return $this->json(DomainSerializer::intersection($intersection));
    }

    /**
     * @OA\Post(
     *     path="/api/road-network/intersections",
     *     operationId="createIntersection",
     *     summary="Create an intersection.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Intersection")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Intersection created.",
     *         @OA\JsonContent(ref="#/components/schemas/Intersection")
     *     ),
     *     @OA\Response(response=422, description="Invalid payload.")
     * )
     */
    private function createIntersection(Request $request): Response
    {
        $intersection = $this->hydrateIntersection($request->getBody());
        $service = $this->container->getRoadNetworkService();
        $service->createIntersection($intersection);

        $created = $service->getIntersection($intersection->id);

        return $this->created($created !== null ? DomainSerializer::intersection($created) : ['id' => $intersection->id]);
    }

    /**
     * @OA\Put(
     *     path="/api/road-network/intersections/{id}",
     *     operationId="updateIntersection",
     *     summary="Update an intersection.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Intersection")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Intersection updated.",
     *         @OA\JsonContent(ref="#/components/schemas/Intersection")
     *     ),
     *     @OA\Response(response=404, description="Intersection not found.")
     * )
     */
    private function updateIntersection(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $payload = $request->getBody();
        $payload['id'] = $id;

        $intersection = $this->hydrateIntersection($payload);
        $service = $this->container->getRoadNetworkService();
        $service->updateIntersection($intersection);

        $updated = $service->getIntersection($id);

        return $this->json($updated !== null ? DomainSerializer::intersection($updated) : ['id' => $id]);
    }

    /**
     * @OA\Delete(
     *     path="/api/road-network/intersections/{id}",
     *     operationId="deleteIntersection",
     *     summary="Delete an intersection.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(response=204, description="Intersection deleted.")
     * )
     */
    private function deleteIntersection(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getRoadNetworkService()->deleteIntersection($id);

        return $this->noContent();
    }

    /**
     * @OA\Get(
     *     path="/api/road-network/segments",
     *     operationId="listRoadSegments",
     *     summary="List road segments.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Road segment collection.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/RoadSegment")
     *             )
     *         )
     *     )
     * )
     */
    private function listRoadSegments(): Response
    {
        $segments = $this->container->getRoadNetworkService()->listRoadSegments();

        return $this->json([
            'data' => DomainSerializer::roadSegments($segments),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/road-network/segments/{id}",
     *     operationId="getRoadSegment",
     *     summary="Get a road segment by id.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Road segment details.",
     *         @OA\JsonContent(ref="#/components/schemas/RoadSegment")
     *     ),
     *     @OA\Response(response=404, description="Road segment not found.")
     * )
     */
    private function getRoadSegment(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $segment = $this->container->getRoadNetworkService()->getRoadSegment($id);
        if ($segment === null) {
            throw new HttpException('Road segment not found.', 404);
        }

        return $this->json(DomainSerializer::roadSegment($segment));
    }

    /**
     * @OA\Post(
     *     path="/api/road-network/segments",
     *     operationId="createRoadSegment",
     *     summary="Create a road segment.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/RoadSegment")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Road segment created.",
     *         @OA\JsonContent(ref="#/components/schemas/RoadSegment")
     *     ),
     *     @OA\Response(response=422, description="Invalid payload.")
     * )
     */
    private function createRoadSegment(Request $request): Response
    {
        $segment = $this->hydrateRoadSegment($request->getBody());
        $service = $this->container->getRoadNetworkService();
        $service->createRoadSegment($segment);

        $created = $service->getRoadSegment($segment->id);

        return $this->created($created !== null ? DomainSerializer::roadSegment($created) : ['id' => $segment->id]);
    }

    /**
     * @OA\Put(
     *     path="/api/road-network/segments/{id}",
     *     operationId="updateRoadSegment",
     *     summary="Update a road segment.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/RoadSegment")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Road segment updated.",
     *         @OA\JsonContent(ref="#/components/schemas/RoadSegment")
     *     ),
     *     @OA\Response(response=404, description="Road segment not found.")
     * )
     */
    private function updateRoadSegment(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $payload = $request->getBody();
        $payload['id'] = $id;

        $segment = $this->hydrateRoadSegment($payload);
        $service = $this->container->getRoadNetworkService();
        $service->updateRoadSegment($segment);

        $updated = $service->getRoadSegment($id);

        return $this->json($updated !== null ? DomainSerializer::roadSegment($updated) : ['id' => $id]);
    }

    /**
     * @OA\Delete(
     *     path="/api/road-network/segments/{id}",
     *     operationId="deleteRoadSegment",
     *     summary="Delete a road segment.",
     *     tags={"Road Network"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(response=204, description="Road segment deleted.")
     * )
     */
    private function deleteRoadSegment(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getRoadNetworkService()->deleteRoadSegment($id);

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateIntersection(array $payload): Intersection
    {
        foreach (['id', 'code', 'controlType', 'numberOfLegs', 'hasCameras', 'aadt', 'spfModelReference', 'geoLocation'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new HttpException(sprintf('Intersection payload missing "%s".', $field), 422);
            }
        }

        $geo = $payload['geoLocation'];
        if (!is_array($geo) || !isset($geo['wkt'])) {
            throw new HttpException('Intersection geoLocation must include "wkt".', 422);
        }

        try {
            $controlType = $payload['controlType'] instanceof IntersectionControlType
                ? $payload['controlType']
                : IntersectionControlType::from((string)$payload['controlType']);
        } catch (\ValueError $error) {
            throw new HttpException('Invalid intersection controlType.', 422, $error);
        }

        return new Intersection(
            id: (int)$payload['id'],
            code: $payload['code'] !== null ? (string)$payload['code'] : null,
            controlType: $controlType,
            numberOfLegs: (int)$payload['numberOfLegs'],
            hasCameras: (bool)$payload['hasCameras'],
            aadt: (int)$payload['aadt'],
            spfModelReference: (string)$payload['spfModelReference'],
            geoLocation: new GeoLocation(
                wkt: (string)$geo['wkt'],
                city: $geo['city'] ?? null,
                street: $geo['street'] ?? null
            )
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateRoadSegment(array $payload): RoadSegment
    {
        foreach (['id', 'code', 'lengthKm', 'laneCount', 'functionalClass', 'speedLimitKmh', 'aadt', 'geoLocation'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new HttpException(sprintf('Road segment payload missing "%s".', $field), 422);
            }
        }

        $geo = $payload['geoLocation'];
        if (!is_array($geo) || !isset($geo['wkt'])) {
            throw new HttpException('Road segment geoLocation must include "wkt".', 422);
        }

        try {
            $functionalClass = $payload['functionalClass'] instanceof FunctionalClass
                ? $payload['functionalClass']
                : FunctionalClass::from((string)$payload['functionalClass']);
        } catch (\ValueError $error) {
            throw new HttpException('Invalid functionalClass value.', 422, $error);
        }

        return new RoadSegment(
            id: (int)$payload['id'],
            code: $payload['code'] !== null ? (string)$payload['code'] : null,
            lengthKm: (float)$payload['lengthKm'],
            laneCount: (int)$payload['laneCount'],
            functionalClass: $functionalClass,
            speedLimitKmh: (int)$payload['speedLimitKmh'],
            aadt: (int)$payload['aadt'],
            geoLocation: new GeoLocation(
                wkt: (string)$geo['wkt'],
                city: $geo['city'] ?? null,
                street: $geo['street'] ?? null
            )
        );
    }
}

