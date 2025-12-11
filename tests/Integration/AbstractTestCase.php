<?php

declare(strict_types=1);

namespace Cardyo\Tests\SpiralOffsetPagination\Integration;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\DatabaseManager;
use Cycle\ORM\EntityManager;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\Schema;
use PHPUnit\Framework\TestCase;
use Spiral\Cycle\DataGrid\Writer\QueryWriter;
use Spiral\DataGrid\Compiler;
use Spiral\DataGrid\GridFactory;
use Spiral\DataGrid\GridSchema;
use Spiral\DataGrid\Input\ArrayInput;

abstract class AbstractTestCase extends TestCase
{
    protected DatabaseManager $dbal;

    protected ORM $orm;

    protected EntityManager $em;

    protected GridFactory $gridFactory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create in-memory SQLite database
        $this->dbal = new DatabaseManager(
            new DatabaseConfig([
                'databases' => [
                    'default' => ['driver' => 'runtime'],
                ],
                'drivers' => [
                    'runtime' => new SQLiteDriverConfig(
                        connection: new MemoryConnectionConfig(),
                        queryCache: true,
                    ),
                ],
            ]),
        );

        // Create ORM with empty schema initially
        $this->orm = new ORM(new Factory($this->dbal), new Schema([]));
        $this->em = new EntityManager($this->orm);

        // Create GridFactory with QueryWriter
        $compiler = new Compiler();
        $compiler->addWriter(new QueryWriter());

        $this->gridFactory = new GridFactory($compiler);
    }

    protected function runMigrations(Schema $schema): void
    {
        $this->orm = $this->orm->with(schema: $schema);

        $registry = $this->orm->getSchema();

        foreach ($registry->getRoles() as $role) {
            $table = $this->dbal->database()->table($registry->define($role, Schema::TABLE));

            $table->getSchema()->declareDropped();
            $table->getSchema()->save();
        }

        foreach ($registry->getRoles() as $role) {
            $table = $this->dbal->database()->table($registry->define($role, Schema::TABLE));

            foreach ($registry->define($role, Schema::COLUMNS) as $column => $definition) {
                $table->getSchema()->column($column)->type($definition);
            }

            $pk = $registry->define($role, Schema::PRIMARY_KEY);
            if (is_string($pk)) {
                $table->getSchema()->setPrimaryKeys([$pk]);
            }

            $table->getSchema()->save();
        }
    }

    protected function createGrid($source, GridSchema $schema, array $input = []): \Spiral\DataGrid\GridInterface
    {
        return $this->gridFactory
            ->withInput(new ArrayInput($input))
            ->create($source, $schema);
    }
}
