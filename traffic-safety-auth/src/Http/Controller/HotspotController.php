<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use HotspotContext\Domain\Factory\HotspotFactory;
use SharedKernel\DTO\HotspotScreeningDTO;
use SharedKernel\DTO\HotspotSearchDTO;
use SharedKernel\Enum\LocationType;
use SharedKernel\Model\Hotspot;
use SharedKernel\Model\Intersection;
use SharedKernel\Model\RoadSegment;

final class HotspotController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('POST', '/api/hotspots', fn(Request $request): Response => $this->createHotspot($request), true, self::ROLE_MANAGER);
        $router->add('GET', '/api/hotspots/{id}', fn(Request $request): Response => $this->getHotspot($request), true, self::ROLE_VIEW);
        $router->add('PUT', '/api/hotspots/{id}', fn(Request $request): Response => $this->updateHotspot($request), true, self::ROLE_MANAGER);
        $router->add('DELETE', '/api/hotspots/{id}', fn(Request $request): Response => $this->deleteHotspot($request), true, self::ROLE_MANAGER);
        $router->add('POST', '/api/hotspots/search', fn(Request $request): Response => $this->searchHotspots($request), true, self::ROLE_ANALYST);
        $router->add('POST', '/api/hotspots/screening', fn(Request $request): Response => $this->screenHotspots($request), true, self::ROLE_ANALYST);
    }

    private function createHotspot(Request $request): Response
    {
        $payload = $request->getBody();
        $hotspot = $this->hydrateHotspot($payload, false);

        $service = $this->container->getHotspotService();
        $service->create($hotspot);

        $created = $service->findById($hotspot->id);

        return $this->created($created !== null ? DomainSerializer::hotspot($created) : ['id' => $hotspot->id]);
    }

    private function getHotspot(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $hotspot = $this->container->getHotspotService()->findById($id);

        if ($hotspot === null) {
            throw new HttpException('Hotspot not found.', 404);
        }

        return $this->json(DomainSerializer::hotspot($hotspot));
    }

    private function updateHotspot(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $payload = $request->getBody();
        $payload['id'] = $id;

        $hotspot = $this->hydrateHotspot($payload, true);
        $service = $this->container->getHotspotService();
        $service->update($hotspot);

        $updated = $service->findById($id);

        return $this->json($updated !== null ? DomainSerializer::hotspot($updated) : ['id' => $id]);
    }

    private function deleteHotspot(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getHotspotService()->delete($id);

        return $this->noContent();
    }

    private function searchHotspots(Request $request): Response
    {
        $payload = $request->getBody();
        $searchDto = HotspotSearchDTO::fromArray($payload);
        $results = $this->container->getHotspotService()->search($searchDto);

        return $this->json([
            'data' => DomainSerializer::hotspots($results),
            'count' => count($results),
        ]);
    }

    private function screenHotspots(Request $request): Response
    {
        $payload = $request->getBody();
        if (!isset($payload['locationType'], $payload['threshold'])) {
            throw new HttpException('Fields "locationType" and "threshold" are required.', 422);
        }

        $dto = HotspotScreeningDTO::fromArray($payload);
        $results = $this->container->getHotspotService()->screeningForHotspots($dto);

        return $this->json([
            'data' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateHotspot(array $payload, bool $requireId): Hotspot
    {
        foreach (['id', 'location', 'period', 'expectedCrashes', 'riskScore', 'status'] as $field) {
            if ($requireId && $field === 'id') {
                continue;
            }
            if (!array_key_exists($field, $payload)) {
                throw new HttpException(sprintf('Field "%s" is required for hotspot payloads.', $field), 422);
            }
        }

        if (!$requireId && !isset($payload['id'])) {
            throw new HttpException('Field "id" is required for hotspot creation.', 422);
        }

        $locationData = $payload['location'];
        if (!is_array($locationData) || !isset($locationData['type'], $locationData['id'])) {
            throw new HttpException('Location must include "type" and "id".', 422);
        }

        $location = $this->resolveLocation(
            $locationData['type'],
            (int)$locationData['id']
        );

        $periodData = $payload['period'] ?? [];
        if (!isset($periodData['startDate'], $periodData['endDate'])) {
            throw new HttpException('Period must include "startDate" and "endDate".', 422);
        }

        $factoryPayload = [
            'id' => (int)$payload['id'],
            'location' => $location,
            'period_start' => $periodData['startDate'],
            'period_end' => $periodData['endDate'],
            'observed_crashes' => $payload['observedCrashes'] ?? [],
            'expected_crashes' => (float)$payload['expectedCrashes'],
            'risk_score' => (float)$payload['riskScore'],
            'status' => $payload['status'],
            'screening_parameters' => $payload['screeningParameters'] ?? null,
        ];

        return HotspotFactory::create($factoryPayload);
    }

    private function resolveLocation(string $type, int $id): RoadSegment|Intersection
    {
        $type = strtolower($type);
        $roadNetwork = $this->container->getRoadNetworkService();

        return match ($type) {
            'road_segment', 'road-segment', LocationType::ROADSEGMENT->value => $roadNetwork->getRoadSegment($id)
                ?? throw new HttpException(sprintf('Road segment %d not found.', $id), 404),
            'intersection', LocationType::INTERSECTION->value => $roadNetwork->getIntersection($id)
                ?? throw new HttpException(sprintf('Intersection %d not found.', $id), 404),
            default => throw new HttpException(sprintf('Unsupported location type "%s".', $type), 422),
        };
    }
}

