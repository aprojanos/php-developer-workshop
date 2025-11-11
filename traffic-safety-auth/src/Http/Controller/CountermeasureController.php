<?php

declare(strict_types=1);

namespace App\Http\Controller;

use AccidentContext\Domain\Factory\AccidentFactory;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use CountermeasureContext\Domain\Factory\CountermeasureFactory;
use SharedKernel\Domain\Event\ProjectEvaluatedEvent;
use SharedKernel\DTO\CountermeasureHotspotFilterDTO;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\LifecycleStatus;
use SharedKernel\Enum\TargetType;
use SharedKernel\Model\Countermeasure;
use SharedKernel\Model\Project;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\ValueObject\TimePeriod;

final class CountermeasureController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('POST', '/api/countermeasures', fn(Request $request): Response => $this->createCountermeasure($request), true, self::ROLE_MANAGER);
        $router->add('GET', '/api/countermeasures', fn(Request $request): Response => $this->listCountermeasures(), true, self::ROLE_VIEW);
        $router->add('GET', '/api/countermeasures/{id}', fn(Request $request): Response => $this->getCountermeasure($request), true, self::ROLE_VIEW);
        $router->add('POST', '/api/countermeasures/hotspot', fn(Request $request): Response => $this->findForHotspot($request), true, self::ROLE_ANALYST);
        $router->add('PUT', '/api/countermeasures/{id}', fn(Request $request): Response => $this->updateCountermeasure($request), true, self::ROLE_MANAGER);
        $router->add('DELETE', '/api/countermeasures/{id}', fn(Request $request): Response => $this->deleteCountermeasure($request), true, self::ROLE_MANAGER);
        $router->add('POST', '/api/countermeasures/recalculate-cmf', fn(Request $request): Response => $this->recalculateCmf($request), true, self::ROLE_ANALYST);
    }

    private function createCountermeasure(Request $request): Response
    {
        $countermeasure = $this->hydrateCountermeasure($request->getBody(), false);
        $service = $this->container->getCountermeasureService();
        $service->create($countermeasure);

        $created = $service->findById($countermeasure->id);

        return $this->created($created !== null ? DomainSerializer::countermeasure($created) : ['id' => $countermeasure->id]);
    }

    private function listCountermeasures(): Response
    {
        $countermeasures = $this->container->getCountermeasureService()->all();

        return $this->json([
            'data' => DomainSerializer::countermeasures($countermeasures),
        ]);
    }

    private function getCountermeasure(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $countermeasure = $this->container->getCountermeasureService()->findById($id);

        if ($countermeasure === null) {
            throw new HttpException('Countermeasure not found.', 404);
        }

        return $this->json(DomainSerializer::countermeasure($countermeasure));
    }

    private function findForHotspot(Request $request): Response
    {
        $payload = $request->getBody();
        $filter = $this->hydrateCountermeasureFilter($payload);

        $results = $this->container->getCountermeasureService()->findForHotspot($filter);

        return $this->json([
            'data' => DomainSerializer::countermeasures($results),
            'count' => count($results),
        ]);
    }

    private function updateCountermeasure(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $payload = $request->getBody();
        $payload['id'] = $id;

        $countermeasure = $this->hydrateCountermeasure($payload, true);
        $service = $this->container->getCountermeasureService();
        $service->update($countermeasure);

        $updated = $service->findById($id);

        return $this->json($updated !== null ? DomainSerializer::countermeasure($updated) : ['id' => $id]);
    }

    private function deleteCountermeasure(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getCountermeasureService()->delete($id);

        return $this->noContent();
    }

    private function recalculateCmf(Request $request): Response
    {
        $payload = $request->getBody();
        if (!isset($payload['project']) || !is_array($payload['project'])) {
            throw new HttpException('Field "project" is required.', 422);
        }
        if (!isset($payload['accident']) || !is_array($payload['accident'])) {
            throw new HttpException('Field "accident" is required.', 422);
        }

        $project = $this->hydrateProject($payload['project']);
        $accident = AccidentFactory::create($payload['accident']);

        $event = new ProjectEvaluatedEvent($project, $accident);
        $this->container->getCountermeasureService()->recalculateCmf($event);

        return $this->json([
            'status' => 'cmf_recalculated',
            'projectId' => $project->id,
            'countermeasureId' => $project->countermeasureId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateCountermeasure(array $payload, bool $requireId): Countermeasure
    {
        foreach (['name', 'target_type'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new HttpException(sprintf('Field "%s" is required.', $key), 422);
            }
        }

        if ($requireId && !isset($payload['id'])) {
            throw new HttpException('Field "id" is required for updates.', 422);
        }

        return $requireId
            ? CountermeasureFactory::createFromArray($payload)
            : CountermeasureFactory::create($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateCountermeasureFilter(array $payload): CountermeasureHotspotFilterDTO
    {
        if (!isset($payload['targetType'])) {
            throw new HttpException('Field "targetType" is required.', 422);
        }

        try {
            $targetType = $payload['targetType'] instanceof TargetType
                ? $payload['targetType']
                : TargetType::from($payload['targetType']);
        } catch (\ValueError $error) {
            throw new HttpException('Invalid targetType value.', 422, $error);
        }

        $collisionTypes = array_map(
            static function ($value): CollisionType {
                if ($value instanceof CollisionType) {
                    return $value;
                }
                return CollisionType::from((string)$value);
            },
            $payload['affectedCollisionTypes'] ?? []
        );

        $severities = array_map(
            static function ($value): InjurySeverity {
                if ($value instanceof InjurySeverity) {
                    return $value;
                }
                return InjurySeverity::from((string)$value);
            },
            $payload['affectedSeverities'] ?? []
        );

        $allowedStatusesRaw = $payload['allowedStatuses'] ?? null;
        $allowedStatuses = null;
        if (is_array($allowedStatusesRaw)) {
            $allowedStatuses = array_map(
                static function ($value): LifecycleStatus {
                    if ($value instanceof LifecycleStatus) {
                        return $value;
                    }
                    return LifecycleStatus::from((string)$value);
                },
                $allowedStatusesRaw
            );
        }

        return new CountermeasureHotspotFilterDTO(
            targetType: $targetType,
            affectedCollisionTypes: $collisionTypes,
            affectedSeverities: $severities,
            allowedStatuses: $allowedStatuses
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateProject(array $payload): Project
    {
        foreach (['id', 'countermeasureId', 'hotspotId', 'period', 'expectedCost', 'actualCost', 'status'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new HttpException(sprintf('Project payload missing "%s".', $key), 422);
            }
        }

        $periodData = $payload['period'];
        if (!is_array($periodData) || !isset($periodData['startDate'], $periodData['endDate'])) {
            throw new HttpException('Project period must include startDate and endDate.', 422);
        }

        $expectedCost = $payload['expectedCost'];
        $actualCost = $payload['actualCost'];

        if (!is_array($expectedCost) || !isset($expectedCost['amount'])) {
            throw new HttpException('Project expectedCost.amount is required.', 422);
        }

        if (!is_array($actualCost) || !isset($actualCost['amount'])) {
            throw new HttpException('Project actualCost.amount is required.', 422);
        }

        try {
            $status = \SharedKernel\Enum\ProjectStatus::from(strtolower((string)$payload['status']));
        } catch (\ValueError $error) {
            throw new HttpException('Invalid project status provided.', 422, $error);
        }

        return new Project(
            id: (int)$payload['id'],
            countermeasureId: (int)$payload['countermeasureId'],
            hotspotId: (int)$payload['hotspotId'],
            period: new TimePeriod(
                new \DateTimeImmutable($periodData['startDate']),
                new \DateTimeImmutable($periodData['endDate'])
            ),
            expectedCost: new MonetaryAmount(
                amount: (float)$expectedCost['amount'],
                currency: $expectedCost['currency'] ?? 'USD'
            ),
            actualCost: new MonetaryAmount(
                amount: (float)$actualCost['amount'],
                currency: $actualCost['currency'] ?? 'USD'
            ),
            status: $status
        );
    }
}

