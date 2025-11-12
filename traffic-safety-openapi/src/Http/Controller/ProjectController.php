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
use OpenApi\Annotations as OA;

final class ProjectController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('POST', '/api/projects', fn(Request $request): Response => $this->createProject($request), true, self::ROLE_MANAGER);
        $router->add('GET', '/api/projects', fn(Request $request): Response => $this->listProjects(), true, self::ROLE_VIEW);
        $router->add('GET', '/api/projects/{id}', fn(Request $request): Response => $this->getProject($request), true, self::ROLE_VIEW);
        $router->add('GET', '/api/projects/hotspot/{hotspotId}', fn(Request $request): Response => $this->projectsByHotspot($request), true, self::ROLE_VIEW);
        $router->add('GET', '/api/projects/countermeasure/{countermeasureId}', fn(Request $request): Response => $this->projectsByCountermeasure($request), true, self::ROLE_VIEW);
        $router->add('GET', '/api/projects/status/{status}', fn(Request $request): Response => $this->projectsByStatus($request), true, self::ROLE_VIEW);
        $router->add('PUT', '/api/projects/{id}', fn(Request $request): Response => $this->updateProject($request), true, self::ROLE_MANAGER);
        $router->add('DELETE', '/api/projects/{id}', fn(Request $request): Response => $this->deleteProject($request), true, self::ROLE_MANAGER);
        $router->add('POST', '/api/projects/{id}/status', fn(Request $request): Response => $this->transitionStatus($request), true, self::ROLE_MANAGER);
        $router->add('POST', '/api/projects/evaluate', fn(Request $request): Response => $this->evaluateProjects($request), true, self::ROLE_ANALYST);
    }

    /**
     * @OA\Post(
     *     path="/api/projects",
     *     operationId="createProject",
     *     summary="Create a new project.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Project")
     *     ),
     *     @OA\Response(
     *         response=201,
         *         description="Project created.",
     *         @OA\JsonContent(ref="#/components/schemas/Project")
     *     ),
     *     @OA\Response(response=422, description="Validation error.")
     * )
     */
    private function createProject(Request $request): Response
    {
        $project = $this->hydrateProject($request->getBody());
        $service = $this->container->getProjectService();

        $service->create($project);

        $created = $service->findById($project->id);

        return $this->created($created !== null ? DomainSerializer::project($created) : ['id' => $project->id]);
    }

    /**
     * @OA\Get(
     *     path="/api/projects",
     *     operationId="listProjects",
     *     summary="List projects.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Project collection.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Project")
     *             )
     *         )
     *     )
     * )
     */
    private function listProjects(): Response
    {
        $projects = $this->container->getProjectService()->all();

        return $this->json([
            'data' => DomainSerializer::projects($projects),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/projects/{id}",
     *     operationId="getProject",
     *     summary="Get a project by id.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project details.",
     *         @OA\JsonContent(ref="#/components/schemas/Project")
     *     ),
     *     @OA\Response(response=404, description="Project not found.")
     * )
     */
    private function getProject(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $project = $this->container->getProjectService()->findById($id);

        if ($project === null) {
            throw new HttpException('Project not found.', 404);
        }

        return $this->json(DomainSerializer::project($project));
    }

    /**
     * @OA\Get(
     *     path="/api/projects/hotspot/{hotspotId}",
     *     operationId="getProjectsByHotspot",
     *     summary="List projects attached to a hotspot.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="hotspotId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projects found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Project")
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     )
     * )
     */
    private function projectsByHotspot(Request $request): Response
    {
        $hotspotId = (int)$this->requireRouteParam($request, 'hotspotId');
        $projects = $this->container->getProjectService()->findByHotspot($hotspotId);

        return $this->json([
            'data' => DomainSerializer::projects($projects),
            'count' => count($projects),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/projects/countermeasure/{countermeasureId}",
     *     operationId="getProjectsByCountermeasure",
     *     summary="List projects by countermeasure.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="countermeasureId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projects found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Project")
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     )
     * )
     */
    private function projectsByCountermeasure(Request $request): Response
    {
        $countermeasureId = (int)$this->requireRouteParam($request, 'countermeasureId');
        $projects = $this->container->getProjectService()->findByCountermeasure($countermeasureId);

        return $this->json([
            'data' => DomainSerializer::projects($projects),
            'count' => count($projects),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/projects/status/{status}",
     *     operationId="getProjectsByStatus",
     *     summary="List projects by status.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projects found.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Project")
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid status provided.")
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/api/projects/{id}",
     *     operationId="updateProject",
     *     summary="Update a project.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Project")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project updated.",
     *         @OA\JsonContent(ref="#/components/schemas/Project")
     *     ),
     *     @OA\Response(response=404, description="Project not found.")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/projects/{id}",
     *     operationId="deleteProject",
     *     summary="Delete a project.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(response=204, description="Project deleted.")
     * )
     */
    private function deleteProject(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getProjectService()->delete($id);

        return $this->noContent();
    }

    /**
     * @OA\Post(
     *     path="/api/projects/{id}/status",
     *     operationId="transitionProjectStatus",
     *     summary="Transition a project's status.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status"},
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project status updated.",
     *         @OA\JsonContent(ref="#/components/schemas/Project")
     *     ),
     *     @OA\Response(response=422, description="Invalid status value.")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/projects/evaluate",
     *     operationId="evaluateProjects",
     *     summary="Trigger evaluation workflows for projects based on an accident.",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"accident"},
     *             @OA\Property(property="accident", type="object", additionalProperties=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Evaluation triggered.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="accidentId", type="integer", format="int64", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid payload.")
     * )
     */
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

