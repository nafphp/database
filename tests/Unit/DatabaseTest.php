<?php

declare(strict_types=1);

namespace Tests\Unit;

use Naf\Database\Core\Database;
use Naf\Database\Exceptions\DatabaseException;
use PDO;
use PDOException;
use Tests\Fixtures\DummyDatabase;
use Tests\NafTestCase;

class DatabaseTest extends NafTestCase
{
    public function testBuildsMysqlDsnWithDefaultPort(): void
    {
        $config = [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'database' => 'testdb',
            'charset'  => 'utf8mb4',
        ];

        $db = new DummyDatabase($config);

        $this->assertSame(
            'mysql:host=localhost;dbname=testdb;port=3306;charset=utf8mb4',
            $db->getLastDsn(),
        );
        $this->assertSame('mysql', $db->usedDriver);
    }

    public function testBuildsPgsqlDsnWithDefaultPort(): void
    {
        $config = [
            'driver'   => 'pgsql',
            'host'     => 'localhost',
            'database' => 'testdb',
            'charset'  => 'utf8mb4',
        ];

        $db = new DummyDatabase($config);

        $this->assertSame('pgsql:host=localhost;dbname=testdb;port=5432', $db->getLastDsn());
        $this->assertSame('pgsql', $db->usedDriver);
    }

    public function testBuildsSqliteDsn(): void
    {
        $config = [
            'driver'   => 'sqlite',
            'database' => '/tmp/testdb.sqlite',
            'charset'  => 'utf8mb4',
        ];

        $db = new DummyDatabase($config);

        $this->assertSame('sqlite:/tmp/testdb.sqlite', $db->getLastDsn());
        $this->assertSame('sqlite', $db->usedDriver);
    }

    public function testBuildsSqliteWithMemoryDsn(): void
    {
        $config = [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'charset'  => 'utf8mb4',
        ];

        $db = new DummyDatabase($config);

        $this->assertSame('sqlite::memory:', $db->getLastDsn());
        $this->assertSame('sqlite', $db->usedDriver);
    }

    public function testBuildsSqliteWithEmptyDatabaseFallbackToMemory(): void
    {
        $config = [
            'driver'  => 'sqlite',
            'charset' => 'utf8mb4',
        ];

        $db = new DummyDatabase($config);

        $this->assertSame('sqlite::memory:', $db->getLastDsn());
        $this->assertSame('sqlite', $db->usedDriver);
    }

    public function testConstructCreatesPdoInstance(): void
    {
        $pdoStub = $this->createStub(PDO::class);

        $config = [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'database' => 'testdb',
            'charset'  => 'utf8mb4',
            'username' => 'user',
            'password' => 'pass',
        ];

        $db = new Database($config, fn() => $pdoStub);

        $this->assertSame($pdoStub, $db->getConnection());
    }

    public function testExceptionWhileConnecting()
    {
        $this->expectException(DatabaseException::class);

        $config = [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'database' => 'testdb',
            'charset'  => 'utf8mb4',
            'username' => 'user',
            'password' => 'pass',
        ];

        new Database($config, function () {
            throw new PDOException('test');
        });
    }

    public function testBuildsMysqlDsnWithCustomPort(): void
    {
        $config = [
            'driver'   => 'mysql',
            'host'     => 'localhost',
            'database' => 'testdb',
            'port'     => 4567,
            'charset'  => 'utf8mb4',
        ];

        $db = new DummyDatabase($config);

        $this->assertSame(
            'mysql:host=localhost;dbname=testdb;port=4567;charset=utf8mb4',
            $db->getLastDsn(),
        );
        $this->assertSame('mysql', $db->usedDriver);
    }

    public function testHelperFunction()
    {
        $this->assertNull(\Naf\Database\database());
    }
}
