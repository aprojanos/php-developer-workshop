<?php

declare(strict_types=1);

namespace App\Http\Controller;

use AccidentContext\Domain\Factory\AccidentFactory;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use SharedKernel\Domain\Event\AccidentCreatedEvent;
use SharedKernel\Enum\ProjectStatus;
use SharedKernel\Model\Project;
use SharedKernel\ValueObject\MonetaryAmount;
use SharedKernel\ValueObject\TimePeriod;

final class ProjectController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('POST', '/api/projects', fn(Request $request): Response => $this->createProject($request));
        $router->add('GET', '/api/projects', fn(Request $request): Response => $this->listProjects());
        $router->add('GET', '/api/projects/{id}', fn(Request $request): Response => $this->getProject($request));
        $router->add('GET', '/api/projects/hotspot/{hotspotId}', fn(Request $request): Response => $this->projectsByHotspot($request));
        $router->add('GET', '/api/projects/countermeasure/{countermeasureId}', fn(Request $request): Response => $this->projectsByCountermeasure($request));
        $router->add('GET', '/api/projects/status/{status}', fn(Request $request): Response => $this->projectsByStatus($request));
        $router->add('PUT', '/api/projects/{id}', fn(Request $request): Response => $this->updateProject($request));
        $router->add('DELETE', '/api/projects/{id}', fn(Request $request): Response => $this->deleteProject($request));
        $router->add('POST', '/api/projects/{id}/status', fn(Request $request): Response => $this->transitionStatus($request));
        $router->add('POST', '/api/projects/evaluate', fn(Request $request): Response => $this->evaluateProjects($request));
    }

    private function createProject(Request $request): Response
    {
        $project = $this->hydrateProject($request->getBody());
        $service = $this->container->getProjectService();

        $service->create($project);

        $created = $service->findById($project->id);

        return $this->created($created !== null ? DomainSerializer::project($created) : ['id' => $project->id]);
    }

    private function listProjects(): Response
    {
        $projects = $this->container->getProjectService()->all();

        return $this->json([
            'data' => DomainSerializer::projects($projects),
        ]);
    }

    private function getProject(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $project = $this->container->getProjectService()->findById($id);

        if ($project === null) {
            throw new HttpException('Project not found.', 404);
        }

        return $this->json(DomainSerializer::project($project));
    }

    private function projectsByHotspot(Request $request): Response
    {
        $hotspotId = (int)$this->requireRouteParam($request, 'hotspotId');
        $projects = $this->container->getProjectService()->findByHotspot($hotspotId);

        return $this->json([
            'data' => DomainSerializer::projects($projects),
            'count' => count($projects),
        ]);
    }

    private function projectsByCountermeasure(Request $request): Response
    {
        $countermeasureId = (int)$this->requireRouteParam($request, 'countermeasureId');
        $projects = $this->container->getProjectService()->findByCountermeasure($countermeasureId);

        return $this->json([
            'data' => DomainSerializer::projects($projects),
            'count' => count($projects),
        ]);
    }

    private function projectsByStatus(Request $request): Response
    {
        $statusValue = strtolower($this->requireRouteParam($request, 'status'));
        try {
            $status = ProjectStatus::from($statusValue);
        } catch (\ValueError $error) {
            throw new HttpException(sprintf('Invalid project status "%s".', $statusValue), 422, $error);
        }

        $projects = $this->container->getProjectService()->findByStatus($status);

        return $this->json([
            'data' => DomainSerializer::projects($projects),
            'count' => count($projects),
        ]);
    }

    private function updateProject(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $payload = $request->getBody();
        $payload['id'] = $id;

        $project = $this->hydrateProject($payload);
        $service = $this->container->getProjectService();
        $service->update($project);

        $updated = $service->findById($id);

        return $this->json($updated !== null ? DomainSerializer::project($updated) : ['id' => $id]);
    }

    private function deleteProject(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getProjectService()->delete($id);

        return $this->noContent();
    }

    private function transitionStatus(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $statusValue = strtolower($this->requireJsonString($request, 'status'));

        try {
            $newStatus = ProjectStatus::from($statusValue);
        } catch (\ValueError $error) {
            throw new HttpException(sprintf('Invalid project status "%s".', $statusValue), 422, $error);
        }

        $project = $this->container->getProjectService()->transitionStatus($id, $newStatus);

        return $this->json(DomainSerializer::project($project));
    }

    private function evaluateProjects(Request $request): Response
    {
        $payload = $request->getBody();
        if (!isset($payload['accident']) || !is_array($payload['accident'])) {
            throw new HttpException('Field "accident" with accident payload is required.', 422);
        }

        $accident = AccidentFactory::create($payload['accident']);
        $event = new AccidentCreatedEvent($accident);
        $this->container->getProjectService()->evaluateProjects($event);

        return $this->json([
            'status' => 'projects_evaluated',
            'accidentId' => $accident->id,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateProject(array $payload): Project
    {
        foreach (['id', 'countermeasureId', 'hotspotId', 'period', 'expectedCost', 'actualCost', 'status'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new HttpException(sprintf('Field "%s" is required to describe a project.', $key), 422);
            }
        }

        if (!is_array($payload['period']) || !isset($payload['period']['startDate'], $payload['period']['endDate'])) {
            throw new HttpException('Field "period" must include "startDate" and "endDate".', 422);
        }

        $period = new TimePeriod(
            new \DateTimeImmutable($payload['period']['startDate']),
            new \DateTimeImmutable($payload['period']['endDate'])
        );

        if (!is_array($payload['expectedCost']) || !isset($payload['expectedCost']['amount'])) {
            throw new HttpException('Field "expectedCost.amount" is required.', 422);
        }
        if (!is_array($payload['actualCost']) || !isset($payload['actualCost']['amount'])) {
            throw new HttpException('Field "actualCost.amount" is required.', 422);
        }

        try {
            $status = ProjectStatus::from(strtolower((string)$payload['status']));
        } catch (\ValueError $error) {
            throw new HttpException('Invalid project status value.', 422, $error);
        }

        return new Project(
            id: (int)$payload['id'],
            countermeasureId: (int)$payload['countermeasureId'],
            hotspotId: (int)$payload['hotspotId'],
            period: $period,
            expectedCost: new MonetaryAmount(
                amount: (float)$payload['expectedCost']['amount'],
                currency: $payload['expectedCost']['currency'] ?? 'USD'
            ),
            actualCost: new MonetaryAmount(
                amount: (float)$payload['actualCost']['amount'],
                currency: $payload['actualCost']['currency'] ?? 'USD'
            ),
            status: $status
        );
    }
}

