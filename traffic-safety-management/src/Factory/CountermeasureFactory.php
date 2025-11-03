<?php
namespace App\Factory;

use App\Model\Countermeasure;
use App\Enum\AccidentType;

/**
 * Build Countermeasure value object from raw arrays.
 */
final class CountermeasureFactory
{
    /**
     * @param array{name:string, description?:string, cost?:float, validAccidentTypes?:array, cmf?:float} $data
     */
    public static function createFromArray(array $data): Countermeasure
    {
        $types = [];
        foreach ($data['validAccidentTypes'] ?? [] as $v) {
            // allow either enum values or enum instances
            if ($v instanceof AccidentType) {
                $types[] = $v;
            } else {
                $types[] = AccidentType::from($v);
            }
        }

        return new Countermeasure(
            $data['name'],
            $data['description'] ?? '',
            (float)($data['cost'] ?? 0.0),
            $types,
            (float)($data['cmf'] ?? 1.0)
        );
    }
}
