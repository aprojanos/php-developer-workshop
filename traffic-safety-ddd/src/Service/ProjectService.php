<?php

namespace App\Service;

use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Contract\ProjectRepositoryInterface;
use SharedKernel\Enum\ProjectStatus;
use App\Model\Project;

final class ProjectService
{
    public function __construct(
        private ProjectRepositoryInterface $repository,
        private ?LoggerInterface $logger = null,
    ) {}

    public function create(Project $project): void
    {
        $this->repository->save($project);

        $this->logger?->info('Project created', $this->projectContext($project));
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    public function findById(int $id): ?Project
    {
        $project = $this->repository->findById($id);

        if ($project !== null) {
            $this->logger?->info('Project retrieved', $this->projectContext($project));
        }

        return $project;
    }

    public function findByHotspot(int $hotspotId): array
    {
        $projects = $this->repository->findByHotspot($hotspotId);

        $this->logger?->info('Projects retrieved for hotspot', [
            'hotspotId' => $hotspotId,
            'count' => count($projects),
        ]);

        return $projects;
    }

    public function findByCountermeasure(int $countermeasureId): array
    {
        $projects = $this->repository->findByCountermeasure($countermeasureId);

        $this->logger?->info('Projects retrieved for countermeasure', [
            'countermeasureId' => $countermeasureId,
            'count' => count($projects),
        ]);

        return $projects;
    }

    public function findByStatus(ProjectStatus $status): array
    {
        $projects = $this->repository->findByStatus($status);

        $this->logger?->info('Projects retrieved by status', [
            'status' => $status->value,
            'count' => count($projects),
        ]);

        return $projects;
    }

    public function update(Project $project): void
    {
        $existing = $this->repository->findById($project->id);
        if ($existing === null) {
            throw new \InvalidArgumentException("Project with ID {$project->id} not found");
        }

        $this->repository->update($project);

        $this->logger?->info('Project updated', $this->projectContext($project));
    }

    public function delete(int $id): void
    {
        $project = $this->repository->findById($id);
        if ($project === null) {
            throw new \InvalidArgumentException("Project with ID {$id} not found");
        }

        $this->repository->delete($id);

        $this->logger?->info('Project deleted', [
            'id' => $id,
            'status' => $project->status->value,
        ]);
    }

    public function transitionStatus(int $projectId, ProjectStatus $newStatus): Project
    {
        $project = $this->repository->findById($projectId);
        if ($project === null) {
            throw new \InvalidArgumentException("Project with ID {$projectId} not found");
        }

        if ($project->status === $newStatus) {
            return $project;
        }

        $updatedProject = new Project(
            id: $project->id,
            countermeasureId: $project->countermeasureId,
            hotspotId: $project->hotspotId,
            period: $project->period,
            expectedCost: $project->expectedCost,
            actualCost: $project->actualCost,
            status: $newStatus
        );

        $this->repository->update($updatedProject);

        $this->logger?->info('Project status transitioned', array_merge(
            $this->projectContext($updatedProject),
            ['previousStatus' => $project->status->value]
        ));

        return $updatedProject;
    }

    /**
     * @return array<string, mixed>
     */
    private function projectContext(Project $project): array
    {
        return [
            'id' => $project->id,
            'countermeasureId' => $project->countermeasureId,
            'hotspotId' => $project->hotspotId,
            'periodStart' => $project->period->startDate->format('c'),
            'periodEnd' => $project->period->endDate->format('c'),
            'expectedCost' => $project->expectedCost->amount,
            'actualCost' => $project->actualCost->amount,
            'status' => $project->status->value,
        ];
    }
}

