<?php

declare(strict_types=1);

use AccidentContext\Application\AccidentService;
use AccidentContext\Domain\Factory\AccidentFactory;
use App\Container;
use App\GraphQL\Accident\AccidentSchemaFactory;
use App\Security\AuthenticatedUser;
use GraphQL\Error\UserError;
use GraphQL\GraphQL;
use PHPUnit\Framework\TestCase;
use SharedKernel\Contract\AccidentRepositoryInterface;
use SharedKernel\DTO\AccidentLocationDTO;
use SharedKernel\DTO\AccidentSearchCriteria;
use SharedKernel\Enum\LocationType;
use SharedKernel\Enum\UserRole;
use SharedKernel\Model\AccidentBase;
use SharedKernel\Model\User;

final class AccidentGraphQLTest extends TestCase
{
    public function testAccidentsQueryReturnsSerializedData(): void
    {
        $accident = AccidentFactory::create([
            'id' => 501,
            'occurredAt' => '2025-01-01T10:00:00+00:00',
            'type' => 'PDO',
            'cost' => 1500.75,
            'roadSegmentId' => 10,
            'distanceFromStart' => 3.5,
            'latitude' => 47.0,
            'longitude' => 19.0,
        ]);

        $repository = new InMemoryAccidentRepository([$accident]);
        $service = new AccidentService($repository);

        $schema = new AccidentSchemaFactory(
            $this->createContainerStub($service),
            $this->createAuthResolver($this->createAuthenticatedUser(UserRole::VIEWER))
        );

        $result = GraphQL::executeQuery(
            $schema->createSchema(),
            '{ accidents { id type cost location { locationType locationId } } }'
        )->toArray();

        $this->assertSame(
            [
                'accidents' => [
                    [
                        'id' => 501,
                        'type' => 'PDO',
                        'cost' => 1500.75,
                        'location' => [
                            'locationType' => 'roadsegment',
                            'locationId' => 10,
                        ],
                    ],
                ],
            ],
            $result['data']
        );
    }

    public function testCreateAccidentRequiresAnalystRole(): void
    {
        $service = new AccidentService(new InMemoryAccidentRepository());

        $schema = new AccidentSchemaFactory(
            $this->createContainerStub($service),
            $this->createAuthResolver($this->createAuthenticatedUser(UserRole::VIEWER))
        );

        $query = 'mutation CreateAccident($input: AccidentInput!) { createAccident(input: $input) { id } }';
        $variables = [
            'input' => [
                'id' => 77,
                'occurredAt' => '2025-02-01T12:00:00+00:00',
                'type' => 'PDO',
                'cost' => 1200.0,
                'location' => [
                    'locationType' => 'roadsegment',
                    'locationId' => 20,
                    'latitude' => 47.1,
                    'longitude' => 19.1,
                    'distanceFromStart' => 1.5,
                ],
            ],
        ];

        $result = GraphQL::executeQuery(
            $schema->createSchema(),
            $query,
            null,
            null,
            $variables
        )->toArray();

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('You are not allowed', $result['errors'][0]['message']);
    }

    public function testCreateAccidentSucceedsForAnalyst(): void
    {
        $repository = new InMemoryAccidentRepository();
        $service = new AccidentService($repository);

        $schema = new AccidentSchemaFactory(
            $this->createContainerStub($service),
            $this->createAuthResolver($this->createAuthenticatedUser(UserRole::ANALYST))
        );

        $query = <<<'GRAPHQL'
mutation CreateAccident($input: AccidentInput!) {
  createAccident(input: $input) {
    id
    cost
    location {
      locationId
    }
  }
}
GRAPHQL;

        $variables = [
            'input' => [
                'id' => 88,
                'occurredAt' => '2025-03-01T16:00:00+00:00',
                'type' => 'PDO',
                'cost' => 900,
                'location' => [
                    'locationType' => 'roadsegment',
                    'locationId' => 30,
                    'latitude' => 47.2,
                    'longitude' => 19.2,
                    'distanceFromStart' => 2.0,
                ],
            ],
        ];

        $result = GraphQL::executeQuery(
            $schema->createSchema(),
            $query,
            null,
            null,
            $variables
        )->toArray();

        $this->assertSame(
            [
                'createAccident' => [
                    'id' => 88,
                    'cost' => 900.0,
                    'location' => [
                        'locationId' => 30,
                    ],
                ],
            ],
            $result['data']
        );

        $stored = $repository->findById(88);
        $this->assertInstanceOf(AccidentBase::class, $stored);
    }

    /**
     * @return callable(?array=):AuthenticatedUser
     */
    private function createAuthResolver(AuthenticatedUser $user): callable
    {
        return function (?array $roles = null) use ($user): AuthenticatedUser {
            $roles ??= ['viewer', 'analyst', 'manager', 'admin'];

            if (!$user->hasAnyRole($roles)) {
                throw new UserError('You are not allowed to access this resource.');
            }

            return $user;
        };
    }

    private function createAuthenticatedUser(UserRole $role): AuthenticatedUser
    {
        $now = new DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $user = new User(
            id: 1,
            email: 'user@example.com',
            passwordHash: 'hash',
            firstName: 'Test',
            lastName: 'User',
            role: $role,
            isActive: true,
            createdAt: $now,
            updatedAt: $now
        );

        return new AuthenticatedUser($user, ['jti' => 'token']);
    }

    private function createContainerStub(AccidentService $service): Container
    {
        return new class($service) extends Container {
            public function __construct(private readonly AccidentService $accidentService)
            {
                parent::__construct(__DIR__);
            }

            public function getAccidentService(): AccidentService
            {
                return $this->accidentService;
            }
        };
    }
}

final class InMemoryAccidentRepository implements AccidentRepositoryInterface
{
    /**
     * @var array<int, AccidentBase>
     */
    private array $items = [];

    /**
     * @param AccidentBase[] $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $accident) {
            $this->items[$accident->id] = $accident;
        }
    }

    public function save(AccidentBase $accident): void
    {
        $this->items[$accident->id] = $accident;
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function findById(int $id): ?AccidentBase
    {
        return $this->items[$id] ?? null;
    }

    public function update(AccidentBase $accident): void
    {
        $this->items[$accident->id] = $accident;
    }

    public function delete(int $id): void
    {
        unset($this->items[$id]);
    }

    public function findByLocation(AccidentLocationDTO $location): array
    {
        return array_values(array_filter(
            $this->items,
            static function (AccidentBase $accident) use ($location): bool {
                $accidentLocation = $accident->location;
                return $accidentLocation->locationType === $location->locationType
                    && $accidentLocation->locationId === $location->locationId;
            }
        ));
    }

    public function search(AccidentSearchCriteria $criteria): array
    {
        return array_values($this->items);
    }
}


