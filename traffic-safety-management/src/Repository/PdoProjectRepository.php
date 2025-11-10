<?php

namespace App\Repository;

use App\Contract\ProjectRepositoryInterface;
use App\Enum\ProjectStatus;
use App\Model\Project;
use App\Repository\Exception\ProjectDataIntegrityException;
use App\ValueObject\MonetaryAmount;
use App\ValueObject\TimePeriod;

final class PdoProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function save(Project $project): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO projects (
            id,
            countermeasure_id,
            hotspot_id,
            period_start,
            period_end,
            expected_cost_amount,
            expected_cost_currency,
            actual_cost_amount,
            actual_cost_currency,
            status
        ) VALUES (
            :id,
            :countermeasure_id,
            :hotspot_id,
            :period_start,
            :period_end,
            :expected_cost_amount,
            :expected_cost_currency,
            :actual_cost_amount,
            :actual_cost_currency,
            :status
        )');

        $stmt->execute($this->projectToParameters($project));
    }

    /** @return Project[] */
    public function all(): array
    {
        return $this->fetchProjects('SELECT * FROM projects ORDER BY id');
    }

    public function findById(int $id): ?Project
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->rowToProject($row) : null;
    }

    /** @return Project[] */
    public function findByHotspot(int $hotspotId): array
    {
        return $this->fetchProjects(
            'SELECT * FROM projects WHERE hotspot_id = :hotspot_id ORDER BY period_start DESC',
            ['hotspot_id' => $hotspotId]
        );
    }

    /** @return Project[] */
    public function findByCountermeasure(int $countermeasureId): array
    {
        return $this->fetchProjects(
            'SELECT * FROM projects WHERE countermeasure_id = :countermeasure_id ORDER BY period_start DESC',
            ['countermeasure_id' => $countermeasureId]
        );
    }

    /** @return Project[] */
    public function findByStatus(ProjectStatus $status): array
    {
        return $this->fetchProjects(
            'SELECT * FROM projects WHERE status = :status ORDER BY period_start DESC',
            ['status' => $status->value]
        );
    }

    public function update(Project $project): void
    {
        $stmt = $this->pdo->prepare('UPDATE projects SET
            countermeasure_id = :countermeasure_id,
            hotspot_id = :hotspot_id,
            period_start = :period_start,
            period_end = :period_end,
            expected_cost_amount = :expected_cost_amount,
            expected_cost_currency = :expected_cost_currency,
            actual_cost_amount = :actual_cost_amount,
            actual_cost_currency = :actual_cost_currency,
            status = :status
            WHERE id = :id');

        $stmt->execute($this->projectToParameters($project));
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $params
     * @return Project[]
     */
    private function fetchProjects(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $projects = [];

        foreach ($rows as $row) {
            $projects[] = $this->rowToProject($row);
        }

        return $projects;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToProject(array $row): Project
    {
        $periodStart = isset($row['period_start'])
            ? new \DateTimeImmutable($row['period_start'])
            : throw new ProjectDataIntegrityException('Project row missing period_start column.');
        $periodEnd = isset($row['period_end'])
            ? new \DateTimeImmutable($row['period_end'])
            : throw new ProjectDataIntegrityException('Project row missing period_end column.');

        $expectedCurrency = $row['expected_cost_currency'] ?? 'USD';
        $actualCurrency = $row['actual_cost_currency'] ?? $expectedCurrency;
        $actualAmount = isset($row['actual_cost_amount']) ? (float)$row['actual_cost_amount'] : 0.0;

        if ($actualAmount < 0) {
            $actualAmount = 0.0;
        }

        return new Project(
            id: (int)$row['id'],
            countermeasureId: (int)$row['countermeasure_id'],
            hotspotId: (int)$row['hotspot_id'],
            period: new TimePeriod($periodStart, $periodEnd),
            expectedCost: new MonetaryAmount(
                amount: (float)$row['expected_cost_amount'],
                currency: $expectedCurrency
            ),
            actualCost: new MonetaryAmount(
                amount: $actualAmount,
                currency: $actualCurrency
            ),
            status: ProjectStatus::from($row['status'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function projectToParameters(Project $project): array
    {
        return [
            'id' => $project->id,
            'countermeasure_id' => $project->countermeasureId,
            'hotspot_id' => $project->hotspotId,
            'period_start' => $project->period->startDate->format('c'),
            'period_end' => $project->period->endDate->format('c'),
            'expected_cost_amount' => $project->expectedCost->amount,
            'expected_cost_currency' => $project->expectedCost->currency,
            'actual_cost_amount' => $project->actualCost->amount,
            'actual_cost_currency' => $project->actualCost->currency,
            'status' => $project->status->value,
        ];
    }
}

