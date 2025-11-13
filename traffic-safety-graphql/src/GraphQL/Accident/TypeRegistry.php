<?php

declare(strict_types=1);

namespace App\GraphQL\Accident;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SharedKernel\Enum\AccidentType;
use SharedKernel\Enum\CauseFactor;
use SharedKernel\Enum\CollisionType;
use SharedKernel\Enum\InjurySeverity;
use SharedKernel\Enum\LocationType;
use SharedKernel\Enum\RoadCondition;
use SharedKernel\Enum\VisibilityCondition;
use SharedKernel\Enum\WeatherCondition;

final class TypeRegistry
{
    /**
     * @var array<string, ObjectType>
     */
    private array $objectTypes = [];

    /**
     * @var array<string, InputObjectType>
     */
    private array $inputTypes = [];

    /**
     * @var array<string, EnumType>
     */
    private array $enumTypes = [];

    public function accident(): ObjectType
    {
        return $this->objectTypes['Accident'] ??= new ObjectType([
            'name' => 'Accident',
            'fields' => function (): array {
                return [
                    'id' => Type::nonNull(Type::int()),
                    'occurredAt' => Type::nonNull(Type::string()),
                    'location' => Type::nonNull($this->accidentLocation()),
                    'cost' => Type::nonNull(Type::float()),
                    'type' => Type::nonNull($this->accidentTypeEnum()),
                    'severity' => $this->injurySeverityEnum(),
                    'collisionType' => $this->collisionTypeEnum(),
                    'causeFactor' => $this->causeFactorEnum(),
                    'weatherCondition' => $this->weatherConditionEnum(),
                    'roadCondition' => $this->roadConditionEnum(),
                    'visibilityCondition' => $this->visibilityConditionEnum(),
                    'injuredPersonsCount' => Type::int(),
                    'locationDescription' => Type::string(),
                    'requiresImmediateAttention' => Type::boolean(),
                    'daysSinceOccurrence' => Type::int(),
                ];
            },
        ]);
    }

    public function accidentLocation(): ObjectType
    {
        return $this->objectTypes['AccidentLocation'] ??= new ObjectType([
            'name' => 'AccidentLocation',
            'fields' => function (): array {
                return [
                    'locationType' => Type::nonNull(Type::string()),
                    'locationId' => Type::nonNull(Type::int()),
                    'latitude' => Type::nonNull(Type::float()),
                    'longitude' => Type::nonNull(Type::float()),
                    'distanceFromStart' => Type::float(),
                ];
            },
        ]);
    }

    public function accidentSearchResult(): ObjectType
    {
        return $this->objectTypes['AccidentSearchResult'] ??= new ObjectType([
            'name' => 'AccidentSearchResult',
            'fields' => function (): array {
                return [
                    'data' => Type::nonNull(Type::listOf(Type::nonNull($this->accident()))),
                    'count' => Type::nonNull(Type::int()),
                ];
            },
        ]);
    }

    public function deletePayload(): ObjectType
    {
        return $this->objectTypes['DeleteAccidentPayload'] ??= new ObjectType([
            'name' => 'DeleteAccidentPayload',
            'fields' => [
                'deletedId' => Type::nonNull(Type::int()),
                'success' => Type::nonNull(Type::boolean()),
            ],
        ]);
    }

    public function accidentInput(): InputObjectType
    {
        return $this->inputTypes['AccidentInput'] ??= new InputObjectType([
            'name' => 'AccidentInput',
            'fields' => function (): array {
                return [
                    'id' => Type::int(),
                    'occurredAt' => Type::string(),
                    'type' => $this->accidentTypeEnum(),
                    'severity' => $this->injurySeverityEnum(),
                    'cost' => Type::float(),
                    'collisionType' => $this->collisionTypeEnum(),
                    'causeFactor' => $this->causeFactorEnum(),
                    'weatherCondition' => $this->weatherConditionEnum(),
                    'roadCondition' => $this->roadConditionEnum(),
                    'visibilityCondition' => $this->visibilityConditionEnum(),
                    'injuredPersonsCount' => Type::int(),
                    'locationDescription' => Type::string(),
                    'location' => $this->accidentLocationInput(),
                ];
            },
        ]);
    }

    public function accidentLocationInput(): InputObjectType
    {
        return $this->inputTypes['AccidentLocationInput'] ??= new InputObjectType([
            'name' => 'AccidentLocationInput',
            'fields' => [
                'locationType' => Type::nonNull(Type::string()),
                'locationId' => Type::nonNull(Type::int()),
                'latitude' => Type::float(),
                'longitude' => Type::float(),
                'distanceFromStart' => Type::float(),
            ],
        ]);
    }

    public function timePeriodInput(): InputObjectType
    {
        return $this->inputTypes['TimePeriodInput'] ??= new InputObjectType([
            'name' => 'TimePeriodInput',
            'fields' => [
                'startDate' => Type::nonNull(Type::string()),
                'endDate' => Type::nonNull(Type::string()),
            ],
        ]);
    }

    public function accidentSearchInput(): InputObjectType
    {
        return $this->inputTypes['AccidentSearchInput'] ??= new InputObjectType([
            'name' => 'AccidentSearchInput',
            'fields' => function (): array {
                return [
                    'occurredAtInterval' => $this->timePeriodInput(),
                    'location' => $this->accidentLocationInput(),
                    'severity' => $this->injurySeverityEnum(),
                    'type' => $this->accidentTypeEnum(),
                    'collisionType' => $this->collisionTypeEnum(),
                    'causeFactor' => $this->causeFactorEnum(),
                    'weatherCondition' => $this->weatherConditionEnum(),
                    'roadCondition' => $this->roadConditionEnum(),
                    'visibilityCondition' => $this->visibilityConditionEnum(),
                    'injuredPersonsCount' => Type::int(),
                ];
            },
        ]);
    }

    public function accidentTypeEnum(): EnumType
    {
        return $this->enumFromBackedEnum('AccidentTypeEnum', AccidentType::class);
    }

    public function injurySeverityEnum(): EnumType
    {
        return $this->enumFromBackedEnum('InjurySeverityEnum', InjurySeverity::class);
    }

    public function collisionTypeEnum(): EnumType
    {
        return $this->enumFromBackedEnum('CollisionTypeEnum', CollisionType::class);
    }

    public function causeFactorEnum(): EnumType
    {
        return $this->enumFromBackedEnum('CauseFactorEnum', CauseFactor::class);
    }

    public function weatherConditionEnum(): EnumType
    {
        return $this->enumFromBackedEnum('WeatherConditionEnum', WeatherCondition::class);
    }

    public function roadConditionEnum(): EnumType
    {
        return $this->enumFromBackedEnum('RoadConditionEnum', RoadCondition::class);
    }

    public function visibilityConditionEnum(): EnumType
    {
        return $this->enumFromBackedEnum('VisibilityConditionEnum', VisibilityCondition::class);
    }

    public function locationTypeEnum(): EnumType
    {
        return $this->enumFromBackedEnum('LocationTypeEnum', LocationType::class);
    }

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    private function enumFromBackedEnum(string $name, string $enumClass): EnumType
    {
        return $this->enumTypes[$name] ??= new EnumType([
            'name' => $name,
            'values' => array_reduce(
                $enumClass::cases(),
                static function (array $carry, \BackedEnum $case): array {
                    $carry[$case->name] = ['value' => $case->value];

                    return $carry;
                },
                []
            ),
        ]);
    }
}


