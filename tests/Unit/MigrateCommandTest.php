<?php

declare(strict_types=1);

namespace Tests\Unit;

use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CLI\Exception\ConsoleException;
use Naf\Database\Commands\MigrateCommand;
use Naf\Database\Core\Database;
use Naf\Database\Support\MigrationRegistry;
use PDO;
use PHPUnit\Framework\TestCase;

use function Naf\app;

final class MigrateCommandTest extends TestCase
{
    private PDO $pdo;
    private array $paths;

    protected function setUp(): void
    {
        $database  = new Database(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->pdo = $database->getConnection();
        app()->container()->set(Database::class, $database);
        $this->paths = MigrationRegistry::getPaths();
        MigrationRegistry::reset();
        MigrationRegistry::addPath(__DIR__ . '/../Fixtures/Migrations/first');
        MigrationRegistry::addPath(__DIR__ . '/../Fixtures/Migrations/second');
    }

    protected function tearDown(): void
    {
        MigrationRegistry::reset();
        foreach ($this->paths as $path) {
            MigrationRegistry::addPath($path);
        }
        require __DIR__ . '/../../bootstrap.php';
    }

    private function runCommand(array $arguments): int
    {
        $command = app()->container()->make(MigrateCommand::class);

        return $command->run(new Input($arguments, $command->getDefinition()), $this->createStub(Output::class));
    }

    public function testShortNameSelectsOnlyOneMigration(): void
    {
        $this->assertSame(0, $this->runCommand(['up', '-n', 'M001Parent']));
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
    }

    public function testMalformedNameNeverRollsBackAllMigrations(): void
    {
        $this->runCommand(['up']);

        foreach ([['--name'], ['--name='], ['-n'], ['--name=M001Parent', '--name=M002Child'], ['--name=M001Parent', '-n', 'M002Child']] as $options) {
            try {
                $this->runCommand(['down', ...$options]);
                $this->fail('An invalid name option must fail before running any migration.');
            } catch (ConsoleException) {
                $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
            }
        }
    }

    public function testLongNameAcceptsQualifiedIdentityAndRepeatedUp(): void
    {
        $arguments = ['up', '--name=Tests\\Fixtures\\Migrations\\M001Parent'];
        $this->assertSame(0, $this->runCommand($arguments));
        $this->assertSame(0, $this->runCommand($arguments));
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        $this->assertSame(0, $this->runCommand(['down', '--name=M001Parent']));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
    }
}
