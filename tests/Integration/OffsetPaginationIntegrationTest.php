<?php

declare(strict_types=1);

namespace Cardyo\Tests\SpiralOffsetPagination\Integration;

use Cardyo\SpiralOffsetPagination\Specification\Pagination\OffsetLimitPaginator;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Schema as ORMSchema;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Select\Repository;
use Cycle\ORM\Select\Source;
use DateTimeImmutable;
use Spiral\DataGrid\GridSchema;
use Spiral\DataGrid\Specification\Filter\Like;
use Spiral\DataGrid\Specification\Sorter\Sorter;
use Spiral\DataGrid\Specification\Value\IntValue;
use Spiral\DataGrid\Specification\Value\RangeValue;
use Spiral\DataGrid\Specification\Value\RangeValue\Boundary;
use Spiral\DataGrid\Specification\Value\StringValue;

class TestCustomer
{
    public ?int $id = null;
    public string $name;
    public DateTimeImmutable $created_at;

    public function __construct(string $name, DateTimeImmutable $created_at)
    {
        $this->name = $name;
        $this->created_at = $created_at;
    }
}

class OffsetPaginationIntegrationTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create database table manually
        $schema = $this->dbal->database()->table('customers')->getSchema();
        $schema->primary('id');
        $schema->string('name');
        $schema->datetime('created_at');
        $schema->save();

        // Define ORM schema manually
        $ormSchema = new ORMSchema([
            TestCustomer::class => [
                SchemaInterface::ROLE => 'customer',
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'customers',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'name', 'created_at'],
                SchemaInterface::TYPECAST => [
                    'id' => 'int',
                    'created_at' => 'datetime',
                ],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
                SchemaInterface::REPOSITORY => Repository::class,
                SchemaInterface::SOURCE => Source::class,
            ],
        ]);

        // Update ORM with new schema
        $this->orm = $this->orm->with(schema: $ormSchema);
        $this->em = new \Cycle\ORM\EntityManager($this->orm);
    }

    private function seedCustomers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $customer = new TestCustomer(
                name: "Customer $i",
                created_at: new DateTimeImmutable("2024-01-01 +{$i} hours"),
            );
            $this->em->persist($customer);
        }
        $this->em->run();
    }

    private function createPaginator(int $defaultLimit = 20): OffsetLimitPaginator
    {
        return new OffsetLimitPaginator(
            defaultLimit: $defaultLimit,
            limitValue: new RangeValue(
                new IntValue(),
                Boundary::including(1),
                Boundary::including(100),
            ),
        );
    }

    // Basic Pagination Tests

    public function testFirstPage(): void
    {
        $this->seedCustomers(50);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            ['paginate' => ['offset' => 0, 'limit' => 10]],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(10, $results);
        $this->assertSame('Customer 1', $results[0]->name);
        $this->assertSame('Customer 10', $results[9]->name);
    }

    public function testSecondPage(): void
    {
        $this->seedCustomers(50);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            ['paginate' => ['offset' => 10, 'limit' => 10]],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(10, $results);
        $this->assertSame('Customer 11', $results[0]->name);
        $this->assertSame('Customer 20', $results[9]->name);
    }

    public function testPartialLastPage(): void
    {
        $this->seedCustomers(25);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            ['paginate' => ['offset' => 20, 'limit' => 10]],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(5, $results);
        $this->assertSame('Customer 21', $results[0]->name);
        $this->assertSame('Customer 25', $results[4]->name);
    }

    // Edge Cases

    public function testEmptyResultSet(): void
    {
        // No seeding - empty database

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            ['paginate' => ['offset' => 0, 'limit' => 10]],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(0, $results);
    }

    public function testSingleResult(): void
    {
        $this->seedCustomers(1);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            ['paginate' => ['offset' => 0, 'limit' => 10]],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(1, $results);
        $this->assertSame('Customer 1', $results[0]->name);
    }

    public function testOffsetBeyondTotalCount(): void
    {
        $this->seedCustomers(10);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            ['paginate' => ['offset' => 100, 'limit' => 10]],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(0, $results);
    }

    // Integration with Filters

    public function testPaginationWithFilter(): void
    {
        $this->seedCustomers(50);

        $schema = new GridSchema();
        $schema->addFilter('search', new Like('name', new StringValue()));
        $schema->setPaginator($this->createPaginator(10));

        // Search for "Customer 1" which matches Customer 1, 10, 11-19
        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            [
                'filter' => ['search' => 'Customer 1'],
                'paginate' => ['offset' => 0, 'limit' => 5],
            ],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(5, $results);
        $this->assertSame('Customer 1', $results[0]->name);
        $this->assertSame('Customer 10', $results[1]->name);
    }

    // Integration with Sorters

    public function testPaginationWithSortAscending(): void
    {
        $this->seedCustomers(20);

        $schema = new GridSchema();
        $schema->addSorter('name', new Sorter('name'));
        $schema->setPaginator($this->createPaginator(5));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            [
                'sort' => ['name' => 'asc'],
                'paginate' => ['offset' => 0, 'limit' => 5],
            ],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(5, $results);
        $this->assertSame('Customer 1', $results[0]->name);
        $this->assertSame('Customer 10', $results[1]->name);
        $this->assertSame('Customer 11', $results[2]->name);
    }

    public function testPaginationWithSortDescending(): void
    {
        $this->seedCustomers(20);

        $schema = new GridSchema();
        $schema->addSorter('name', new Sorter('name'));
        $schema->setPaginator($this->createPaginator(5));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            [
                'sort' => ['name' => 'desc'],
                'paginate' => ['offset' => 0, 'limit' => 5],
            ],
        );

        $results = iterator_to_array($grid);

        $this->assertCount(5, $results);
        $this->assertSame('Customer 9', $results[0]->name);
        $this->assertSame('Customer 8', $results[1]->name);
    }

    // Total Count Tests

    public function testPaginationMetadata(): void
    {
        $this->seedCustomers(50);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            ['paginate' => ['offset' => 20, 'limit' => 10]],
        );

        // Trigger iteration to populate options
        iterator_to_array($grid);

        $paginator = $grid->getOption(\Spiral\DataGrid\GridInterface::PAGINATOR);

        $this->assertIsArray($paginator);
        $this->assertArrayHasKey('offset', $paginator);
        $this->assertArrayHasKey('limit', $paginator);
        $this->assertSame(20, $paginator['offset']);
        $this->assertSame(10, $paginator['limit']);
    }

    public function testPaginationWithCount(): void
    {
        $this->seedCustomers(50);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(10));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            [
                'paginate' => ['offset' => 0, 'limit' => 10],
                'fetchCount' => 1,
            ],
        );

        // Trigger iteration to populate options
        iterator_to_array($grid);

        $count = $grid->getOption(\Spiral\DataGrid\GridInterface::COUNT);

        $this->assertSame(50, $count);
    }

    public function testCountRespectsFilters(): void
    {
        $this->seedCustomers(50);

        $schema = new GridSchema();
        $schema->addFilter('search', new Like('name', new StringValue()));
        $schema->setPaginator($this->createPaginator(10));

        // Search for "Customer 1" which matches Customer 1, 10, 11-19 (11 results)
        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            [
                'filter' => ['search' => 'Customer 1'],
                'paginate' => ['offset' => 0, 'limit' => 5],
                'fetchCount' => 1,
            ],
        );

        // Trigger iteration
        iterator_to_array($grid);

        $count = $grid->getOption(\Spiral\DataGrid\GridInterface::COUNT);

        $this->assertSame(11, $count);
    }

    // Default Values Test

    public function testDefaultPaginationValues(): void
    {
        $this->seedCustomers(50);

        $schema = new GridSchema();
        $schema->setPaginator($this->createPaginator(20));

        $grid = $this->createGrid(
            $this->orm->getRepository(TestCustomer::class)->select(),
            $schema,
            [], // No pagination parameters
        );

        $results = iterator_to_array($grid);

        // Should use default limit of 20
        $this->assertCount(20, $results);

        $paginator = $grid->getOption(\Spiral\DataGrid\GridInterface::PAGINATOR);
        $this->assertSame(0, $paginator['offset']);
        $this->assertSame(20, $paginator['limit']);
    }
}
