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

    public function testConnectionErrorsHideDetailsButRetainTheCause(): void
    {
        $cause = new PDOException('host=private.example username=secret password=hunter2');

        try {
            new Database(['driver' => 'mysql'], static fn() => throw $cause);
            self::fail('Expected a connection error.');
        } catch (DatabaseException $exception) {
            self::assertSame('Database connection failed.', $exception->getMessage());
            self::assertSame($cause, $exception->getPrevious());
        }
    }

    public function testDriverOptionsReachTheConnectionFactory(): void
    {
        foreach (['mysql', 'pgsql', 'sqlite'] as $driver) {
            $pdo   = $this->createStub(PDO::class);
            $calls = 0;
            new Database(
                ['driver' => $driver, 'username' => 'user', 'password' => 'secret'],
                function ($dsn, $username, $password, $options) use ($driver, $pdo, &$calls) {
                    ++$calls;
                    self::assertStringStartsWith($driver . ':', $dsn);
                    self::assertSame('user', $username);
                    self::assertSame('secret', $password);
                    self::assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
                    self::assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
                    if ($driver === 'mysql') {
                        self::assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
                    } else {
                        self::assertArrayNotHasKey(PDO::ATTR_EMULATE_PREPARES, $options);
                    }

                    return $pdo;
                },
            );
            self::assertSame(1, $calls);
        }
    }

    public function testSqlitePreparedValuesRemainDataAndRowsAreAssociative(): void
    {
        $pdo = (new Database(['driver' => 'sqlite']))->getConnection();
        $pdo->exec('CREATE TABLE items (name TEXT)');
        $value     = "x'); DROP TABLE items; --";
        $statement = $pdo->prepare('INSERT INTO items (name) VALUES (:name)');
        $statement->execute(['name' => $value]);
        self::assertSame(['name' => $value], $pdo->query('SELECT name FROM items')->fetch());
        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testNetworkDsnValuesCannotInjectAdditionalOptions(): void
    {
        foreach (['mysql', 'pgsql'] as $driver) {
            foreach (['host', 'database', 'port', 'charset'] as $key) {
                foreach (['value;host=attacker', "value\0ignored"] as $value) {
                    try {
                        new DummyDatabase(['driver' => $driver, $key => $value]);
                        self::fail('Expected invalid DSN input to be rejected.');
                    } catch (DatabaseException $exception) {
                        self::assertSame('Invalid connection parameter: ' . $key, $exception->getMessage());
                    }
                }
            }
        }
    }

    public function testPreparedStatementsAgainstConfiguredNetworkDriver(): void
    {
        $driver = getenv('NAF_TEST_DB_DRIVER');
        if ($driver !== 'mysql' && $driver !== 'pgsql') {
            self::markTestSkipped('Set NAF_TEST_DB_DRIVER, NAF_TEST_DB_PORT and NAF_TEST_DB_PASSWORD for disposable driver tests.');
        }
        $pdo = (new Database([
            'driver'   => $driver,
            'host'     => '127.0.0.1',
            'port'     => getenv('NAF_TEST_DB_PORT'),
            'database' => 'naf_security',
            'username' => $driver === 'mysql' ? 'root' : 'postgres',
            'password' => getenv('NAF_TEST_DB_PASSWORD'),
        ]))->getConnection();
        if ($driver === 'mysql') {
            self::assertFalse((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        }
        $pdo->exec('CREATE TEMPORARY TABLE naf_security_items (name VARCHAR(255))');
        $value     = "x'); DROP TABLE naf_security_items; --";
        $statement = $pdo->prepare('INSERT INTO naf_security_items (name) VALUES (:name)');
        $statement->execute(['name' => $value]);
        self::assertSame(['name' => $value], $pdo->query('SELECT name FROM naf_security_items')->fetch());
        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testHelperFunction()
    {
        $this->assertNull(\Naf\Database\database());
    }
}
