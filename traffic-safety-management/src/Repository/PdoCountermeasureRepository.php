<?php
namespace App\Repository;

use App\Model\Countermeasure;
use App\DTO\CountermeasureHotspotFilterDTO;
use App\Factory\CountermeasureFactory;
use App\Contract\CountermeasureRepositoryInterface;
use App\Enum\CollisionType;
use App\Enum\InjurySeverity;
use App\ValueObject\MonetaryAmount;

final class PdoCountermeasureRepository implements CountermeasureRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    public function save(Countermeasure $countermeasure): void
    {
        $applicabilityRules = $this->serializeApplicabilityRules($countermeasure);

        $stmt = $this->pdo->prepare('INSERT INTO countermeasures (
            id, name, target_type, applicability_rules,
            affected_collision_types, affected_severities, cmf,
            lifecycle_status, implementation_cost_amount, implementation_cost_currency,
            expected_annual_savings, evidence
        ) VALUES (
            :id, :name, :target_type, :applicability_rules,
            :affected_collision_types, :affected_severities, :cmf,
            :lifecycle_status, :implementation_cost_amount, :implementation_cost_currency,
            :expected_annual_savings, :evidence
        )');

        $stmt->execute([
            'id' => $countermeasure->id,
            'name' => $countermeasure->name,
            'target_type' => $countermeasure->getTargetType()->value,
            'applicability_rules' => json_encode($applicabilityRules),
            'affected_collision_types' => $this->serializeCollisionTypes($countermeasure->affectedCollisionTypes),
            'affected_severities' => $this->serializeSeverities($countermeasure->affectedSeverities),
            'cmf' => $countermeasure->cmf,
            'lifecycle_status' => $countermeasure->lifecycleStatus->value,
            'implementation_cost_amount' => $countermeasure->implementationCost->amount,
            'implementation_cost_currency' => $countermeasure->implementationCost->currency,
            'expected_annual_savings' => $countermeasure->expectedAnnualSavings,
            'evidence' => $countermeasure->evidence,
        ]);
    }

    /** @return Countermeasure[] */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM countermeasures');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->rowToCountermeasure($row);
        }
        return $result;
    }

    public function findById(int $id): ?Countermeasure
    {
        $stmt = $this->pdo->prepare('SELECT * FROM countermeasures WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->rowToCountermeasure($row);
    }

    /** @return Countermeasure[] */
    public function findForHotspot(CountermeasureHotspotFilterDTO $filter): array
    {
        $statusPlaceholders = [];
        $params = [
            'target_type' => $filter->targetType->value,
        ];

        foreach ($filter->allowedStatuses as $index => $status) {
            $placeholder = ':status_' . $index;
            $statusPlaceholders[] = $placeholder;
            $params['status_' . $index] = $status->value;
        }

        if ($statusPlaceholders === []) {
            return [];
        }

        $sql = sprintf(
            'SELECT * FROM countermeasures WHERE target_type = :target_type AND lifecycle_status IN (%s) ORDER BY cmf DESC',
            implode(', ', $statusPlaceholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $countermeasures = [];
        foreach ($rows as $row) {
            $countermeasure = $this->rowToCountermeasure($row);

            if (!$this->matchesCollisionTypes($countermeasure, $filter->affectedCollisionTypes)) {
                continue;
            }

            if (!$this->matchesSeverities($countermeasure, $filter->affectedSeverities)) {
                continue;
            }

            $countermeasures[] = $countermeasure;
        }

        return $countermeasures;
    }

    public function update(Countermeasure $countermeasure): void
    {
        $applicabilityRules = $this->serializeApplicabilityRules($countermeasure);

        $stmt = $this->pdo->prepare('UPDATE countermeasures
            SET name = :name,
                target_type = :target_type,
                applicability_rules = :applicability_rules,
                affected_collision_types = :affected_collision_types,
                affected_severities = :affected_severities,
                cmf = :cmf,
                lifecycle_status = :lifecycle_status,
                implementation_cost_amount = :implementation_cost_amount,
                implementation_cost_currency = :implementation_cost_currency,
                expected_annual_savings = :expected_annual_savings,
                evidence = :evidence
            WHERE id = :id');

        $stmt->execute([
            'id' => $countermeasure->id,
            'name' => $countermeasure->name,
            'target_type' => $countermeasure->getTargetType()->value,
            'applicability_rules' => json_encode($applicabilityRules),
            'affected_collision_types' => $this->serializeCollisionTypes($countermeasure->affectedCollisionTypes),
            'affected_severities' => $this->serializeSeverities($countermeasure->affectedSeverities),
            'cmf' => $countermeasure->cmf,
            'lifecycle_status' => $countermeasure->lifecycleStatus->value,
            'implementation_cost_amount' => $countermeasure->implementationCost->amount,
            'implementation_cost_currency' => $countermeasure->implementationCost->currency,
            'expected_annual_savings' => $countermeasure->expectedAnnualSavings,
            'evidence' => $countermeasure->evidence,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM countermeasures WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function rowToCountermeasure(array $row): Countermeasure
    {
        $applicabilityRules = json_decode($row['applicability_rules'] ?? '{}', true) ?: [];

        return CountermeasureFactory::createFromArray([
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'target_type' => $row['target_type'],
            'applicability_rules' => $applicabilityRules,
            'affected_collision_types' => $this->deserializeCollisionTypes($row['affected_collision_types'] ?? '[]'),
            'affected_severities' => $this->deserializeSeverities($row['affected_severities'] ?? '[]'),
            'cmf' => (float)$row['cmf'],
            'lifecycle_status' => $row['lifecycle_status'],
            'implementation_cost' => [
                'amount' => (float)$row['implementation_cost_amount'],
                'currency' => $row['implementation_cost_currency'] ?? 'USD',
            ],
            'expected_annual_savings' => isset($row['expected_annual_savings']) ? (float)$row['expected_annual_savings'] : null,
            'evidence' => $row['evidence'] ?? null,
        ]);
    }

    private function serializeApplicabilityRules(Countermeasure $countermeasure): array
    {
        if ($countermeasure instanceof \App\Model\IntersectionCountermeasure) {
            return [
                'intersection_types' => array_map(
                    fn($type) => $type->value,
                    $countermeasure->applicabilityRules->intersectionTypes
                ),
                'intersection_control_types' => array_map(
                    fn($type) => $type->value,
                    $countermeasure->applicabilityRules->intersectionControlTypes
                ),
            ];
        }

        if ($countermeasure instanceof \App\Model\RoadSegmentCountermeasure) {
            return [
                'road_classifications' => array_map(
                    fn($classification) => $classification->value,
                    $countermeasure->applicabilityRules->roadClassifications
                ),
            ];
        }

        return [];
    }

    /**
     * @param array<CollisionType> $collisionTypes
     */
    private function serializeCollisionTypes(array $collisionTypes): string
    {
        $values = array_map(fn($type) => $type->value, $collisionTypes);
        return json_encode($values);
    }

    /**
     * @return array<string>
     */
    private function deserializeCollisionTypes(string $json): array
    {
        return json_decode($json, true) ?: [];
    }

    /**
     * @param array<InjurySeverity> $severities
     */
    private function serializeSeverities(array $severities): string
    {
        $values = array_map(fn($severity) => $severity->value, $severities);
        return json_encode($values);
    }

    /**
     * @return array<string>
     */
    private function deserializeSeverities(string $json): array
    {
        return json_decode($json, true) ?: [];
    }

    /**
     * @param array<\App\Enum\CollisionType> $requiredTypes
     */
    private function matchesCollisionTypes(Countermeasure $countermeasure, array $requiredTypes): bool
    {
        if ($requiredTypes === []) {
            return true;
        }

        foreach ($requiredTypes as $requiredType) {
            if (!in_array($requiredType, $countermeasure->affectedCollisionTypes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<\App\Enum\InjurySeverity> $requiredSeverities
     */
    private function matchesSeverities(Countermeasure $countermeasure, array $requiredSeverities): bool
    {
        if ($requiredSeverities === []) {
            return true;
        }

        foreach ($requiredSeverities as $requiredSeverity) {
            if (!in_array($requiredSeverity, $countermeasure->affectedSeverities, true)) {
                return false;
            }
        }

        return true;
    }
}
