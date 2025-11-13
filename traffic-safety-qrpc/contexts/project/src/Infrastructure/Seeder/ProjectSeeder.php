<?php

namespace ProjectContext\Infrastructure\Seeder;

use ProjectContext\Infrastructure\Seeder\Exception\MissingProjectReferenceException;
use Random\Randomizer;
use SharedKernel\Enum\ProjectStatus;

final class ProjectSeeder
{
    private int $nextId;

    /**
     * @var array<int, array{id: int}>
     */
    private array $countermeasures = [];

    /**
     * @var array<int, array{id: int}>
     */
    private array $hotspots = [];

    private readonly Randomizer $randomizer;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->randomizer = new Randomizer();
        $this->nextId = $this->fetchMaxId();
        $this->countermeasures = $this->fetchCountermeasures();
        $this->hotspots = $this->fetchHotspots();
    }

    public function run(int $count = 5, bool $purgeExisting = true): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('ProjectSeeder requires at least one project to seed.');
        }

        if ($purgeExisting) {
            $this->purge();
            $this->nextId = 0;
        }

        if ($this->countermeasures === []) {
            throw new MissingProjectReferenceException('Cannot seed projects without countermeasures.');
        }

        if ($this->hotspots === []) {
            throw new MissingProjectReferenceException('Cannot seed projects without hotspots.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO projects (
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
            )'
        );

        for ($i = 0; $i < $count; $i++) {
            $payload = $this->generateProjectPayload();

            $stmt->execute([
                'id' => $payload['id'],
                'countermeasure_id' => $payload['countermeasure_id'],
                'hotspot_id' => $payload['hotspot_id'],
                'period_start' => $payload['period_start']->format(DATE_ATOM),
                'period_end' => $payload['period_end']->format(DATE_ATOM),
                'expected_cost_amount' => $payload['expected_cost_amount'],
                'expected_cost_currency' => $payload['expected_cost_currency'],
                'actual_cost_amount' => $payload['actual_cost_amount'],
                'actual_cost_currency' => $payload['actual_cost_currency'],
                'status' => $payload['status']->value,
            ]);
        }
    }

    public function purge(): void
    {
        $this->pdo->exec('DELETE FROM projects');
    }

    private function fetchMaxId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM projects');
        $result = $stmt !== false ? $stmt->fetchColumn() : 0;

        return (int)$result;
    }

    /**
     * @return array<int, array{id: int}>
     */
    private function fetchCountermeasures(): array
    {
        $stmt = $this->pdo->query('SELECT id FROM countermeasures');
        if ($stmt === false) {
            return [];
        }

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($row['id'])) {
                continue;
            }

            $result[] = ['id' => (int)$row['id']];
        }

        return $result;
    }

    /**
     * @return array<int, array{id: int}>
     */
    private function fetchHotspots(): array
    {
        $stmt = $this->pdo->query('SELECT id FROM hotspots');
        if ($stmt === false) {
            return [];
        }

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($row['id'])) {
                continue;
            }

            $result[] = ['id' => (int)$row['id']];
        }

        return $result;
    }

    /**
     * @return array{
     *     id: int,
     *     countermeasure_id: int,
     *     hotspot_id: int,
     *     period_start: \DateTimeImmutable,
     *     period_end: \DateTimeImmutable,
     *     expected_cost_amount: float,
     *     expected_cost_currency: string,
     *     actual_cost_amount: float|null,
     *     actual_cost_currency: ?string,
     *     status: ProjectStatus
     * }
     */
    private function generateProjectPayload(): array
    {
        $countermeasure = $this->countermeasures[array_rand($this->countermeasures)];
        $hotspot = $this->hotspots[array_rand($this->hotspots)];
        [$periodStart, $periodEnd] = $this->randomPeriod();
        $status = $this->randomStatus();
        $expectedCost = $this->randomMoneyAmount(80000, 500000);

        $actualCost = null;
        $actualCurrency = null;

        if (in_array($status, [ProjectStatus::IMPLEMENTED, ProjectStatus::CLOSED], true)) {
            $variance = $this->randomizer->getFloat(0.85, 1.15);
            $actualCost = round($expectedCost * $variance, 2);
            $actualCurrency = 'USD';
        }

        return [
            'id' => $this->generateId(),
            'countermeasure_id' => $countermeasure['id'],
            'hotspot_id' => $hotspot['id'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'expected_cost_amount' => $expectedCost,
            'expected_cost_currency' => 'USD',
            'actual_cost_amount' => $actualCost,
            'actual_cost_currency' => $actualCurrency ?? 'USD',
            'status' => $status,
        ];
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function randomPeriod(): array
    {
        $start = (new \DateTimeImmutable('now'))
            ->sub(new \DateInterval('P' . $this->randomizer->getInt(30, 720) . 'D'));
        $end = $start->add(new \DateInterval('P' . $this->randomizer->getInt(90, 540) . 'D'));

        return [$start, $end];
    }

    private function randomStatus(): ProjectStatus
    {
        $cases = ProjectStatus::cases();

        return $cases[array_rand($cases)];
    }

    private function randomMoneyAmount(int $min, int $max): float
    {
        return round((float)$this->randomizer->getInt($min, $max), 2);
    }

    private function generateId(): int
    {
        $this->nextId++;

        return $this->nextId;
    }
}

