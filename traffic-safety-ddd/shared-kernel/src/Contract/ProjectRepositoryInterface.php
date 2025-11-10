<?php

namespace SharedKernel\Contract;

use SharedKernel\Enum\ProjectStatus;
use SharedKernel\Model\Project;

interface ProjectRepositoryInterface
{
    public function save(Project $project): void;

    /** @return Project[] */
    public function all(): array;

    public function findById(int $id): ?Project;

    /** @return Project[] */
    public function findByHotspot(int $hotspotId): array;

    /** @return Project[] */
    public function findByCountermeasure(int $countermeasureId): array;

    /** @return Project[] */
    public function findByStatus(ProjectStatus $status): array;

    public function update(Project $project): void;

    public function delete(int $id): void;
}

