<?php

declare(strict_types=1);

namespace App\GraphQL\Accident;

use AccidentContext\Application\AccidentService;
use AccidentContext\Domain\Factory\AccidentFactory;
use App\Accident\AccidentPayloadBuilder;
use App\Container;
use App\Http\Serializer\DomainSerializer;
use App\Security\AuthenticatedUser;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use SharedKernel\DTO\AccidentSearchDTO;

final class AccidentSchemaFactory
{
    private const ROLE_VIEW = ['viewer', 'analyst', 'manager', 'admin'];
    private const ROLE_ANALYST = ['analyst', 'manager', 'admin'];
    private const ROLE_MANAGER = ['manager', 'admin'];

    /**
     * @param \Closure(?array=):AuthenticatedUser $authResolver
     */
    public function __construct(
        private readonly Container $container,
        private readonly \Closure $authResolver,
    ) {
        $this->types = new TypeRegistry();
    }

    private TypeRegistry $types;

    public function createSchema(): Schema
    {
        return new Schema([
            'query' => $this->createQueryType(),
            'mutation' => $this->createMutationType(),
        ]);
    }

    private function createQueryType(): ObjectType
    {
        return new ObjectType([
            'name' => 'AccidentQuery',
            'fields' => function (): array {
                return [
                    'accidents' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($this->types->accident()))),
                        'resolve' => function (): array {
                            $this->requireRole(self::ROLE_VIEW);

                            $accidents = $this->service()->all();

                            return DomainSerializer::accidents($accidents);
                        },
                    ],
                    'accident' => [
                        'type' => $this->types->accident(),
                        'args' => [
                            'id' => Type::nonNull(Type::int()),
                        ],
                        'resolve' => function ($root, array $args): ?array {
                            $this->requireRole(self::ROLE_VIEW);

                            $accident = $this->service()->findById((int)$args['id']);
                            if ($accident === null) {
                                throw new UserError('Accident not found.');
                            }

                            return DomainSerializer::accident($accident);
                        },
                    ],
                    'totalEstimatedAccidentCost' => [
                        'type' => Type::nonNull(Type::float()),
                        'resolve' => function (): float {
                            $this->requireRole(self::ROLE_VIEW);

                            return $this->service()->totalEstimatedCost();
                        },
                    ],
                    'searchAccidents' => [
                        'type' => Type::nonNull($this->types->accidentSearchResult()),
                        'args' => [
                            'criteria' => $this->types->accidentSearchInput(),
                        ],
                        'resolve' => function ($root, array $args): array {
                            $this->requireRole(self::ROLE_VIEW);

                            $input = $args['criteria'] ?? [];
                            if ($input === null) {
                                $input = [];
                            }
                            if (!is_array($input)) {
                                throw new UserError('Search criteria must be an object.');
                            }

                            try {
                                $dto = AccidentSearchDTO::fromArray(AccidentInputMapper::toSearchCriteria($input));
                            } catch (\Throwable $exception) {
                                throw new UserError($exception->getMessage());
                            }

                            $results = $this->service()->search($dto);

                            return [
                                'data' => DomainSerializer::accidents($results),
                                'count' => count($results),
                            ];
                        },
                    ],
                ];
            },
        ]);
    }

    private function createMutationType(): ObjectType
    {
        return new ObjectType([
            'name' => 'AccidentMutation',
            'fields' => function (): array {
                return [
                    'createAccident' => [
                        'type' => $this->types->accident(),
                        'args' => [
                            'input' => Type::nonNull($this->types->accidentInput()),
                        ],
                        'resolve' => function ($root, array $args): array {
                            $this->requireRole(self::ROLE_ANALYST);

                            $input = $args['input'];
                            if (!is_array($input)) {
                                throw new UserError('Accident input must be an object.');
                            }

                            try {
                                $payload = AccidentInputMapper::toAccidentPayload($input);
                                $accident = AccidentFactory::create($payload);
                                $this->service()->create($accident);
                                $created = $this->service()->findById($accident->id);
                            } catch (\Throwable $exception) {
                                throw new UserError($exception->getMessage());
                            }

                            if ($created === null) {
                                return ['id' => $accident->id];
                            }

                            return DomainSerializer::accident($created);
                        },
                    ],
                    'updateAccident' => [
                        'type' => $this->types->accident(),
                        'args' => [
                            'id' => Type::nonNull(Type::int()),
                            'input' => Type::nonNull($this->types->accidentInput()),
                        ],
                        'resolve' => function ($root, array $args): array {
                            $this->requireRole(self::ROLE_ANALYST);

                            $id = (int)$args['id'];
                            $input = $args['input'];
                            if (!is_array($input)) {
                                throw new UserError('Accident input must be an object.');
                            }

                            $service = $this->service();
                            $existing = $service->findById($id);
                            if ($existing === null) {
                                throw new UserError('Accident not found.');
                            }

                            try {
                                $payload = AccidentInputMapper::toAccidentPayload($input);
                                $basePayload = AccidentPayloadBuilder::toFactoryPayload($existing);
                                $mergedPayload = array_replace($basePayload, $payload);
                                $mergedPayload['id'] = $id;

                                $accident = AccidentFactory::create($mergedPayload);
                                $service->update($accident);
                                $updated = $service->findById($id);
                            } catch (\Throwable $exception) {
                                throw new UserError($exception->getMessage());
                            }

                            if ($updated === null) {
                                return ['id' => $id];
                            }

                            return DomainSerializer::accident($updated);
                        },
                    ],
                    'deleteAccident' => [
                        'type' => Type::nonNull($this->types->deletePayload()),
                        'args' => [
                            'id' => Type::nonNull(Type::int()),
                        ],
                        'resolve' => function ($root, array $args): array {
                            $this->requireRole(self::ROLE_MANAGER);

                            $id = (int)$args['id'];

                            try {
                                $this->service()->delete($id);
                            } catch (\Throwable $exception) {
                                throw new UserError($exception->getMessage());
                            }

                            return [
                                'deletedId' => $id,
                                'success' => true,
                            ];
                        },
                    ],
                    'calculateAccidentTotalCost' => [
                        'type' => Type::nonNull(Type::float()),
                        'args' => [
                            'accidents' => Type::nonNull(Type::listOf(Type::nonNull($this->types->accidentInput()))),
                        ],
                        'resolve' => function ($root, array $args): float {
                            $this->requireRole(self::ROLE_ANALYST);

                            $accidentsInput = $args['accidents'];
                            if (!is_array($accidentsInput)) {
                                throw new UserError('Accidents input must be an array.');
                            }

                            try {
                                $accidents = array_map(
                                    static fn(array $item) => AccidentFactory::create(AccidentInputMapper::toAccidentPayload($item)),
                                    $accidentsInput
                                );
                            } catch (\Throwable $exception) {
                                throw new UserError($exception->getMessage());
                            }

                            return $this->service()->calculateTotalCost($accidents);
                        },
                    ],
                ];
            },
        ]);
    }

    /**
     * @param list<string>|null $roles
     */
    private function requireRole(?array $roles = null): AuthenticatedUser
    {
        $resolver = $this->authResolver;

        return $resolver($roles ?? self::ROLE_VIEW);
    }

    private function service(): AccidentService
    {
        return $this->container->getAccidentService();
    }
}


