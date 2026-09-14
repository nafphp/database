<?php

declare(strict_types=1);

namespace Naf\Database\Core;

use Naf\Database\Exceptions\DatabaseException;
use PDO;
use PDOException;

class Database
{
    protected PDO $pdo;

    public function __construct(array $config, ?callable $pdoFactory = null)
    {
        $dsn      = $this->buildDsn($config);
        $options  = $this->pdoOptions();
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        try {
            $pdoFactory ??= fn() => new PDO($dsn, $username, $password, $options);
            $this->pdo = $pdoFactory();
        } catch (PDOException $e) {
            throw new DatabaseException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Erzeugt den DSN je nach Treiber.
     */
    protected function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            return $this->buildSqliteDsn($config);
        }

        $defaultPort = $this->defaultPortForDriver($driver);
        $port        = $config['port'] ?? $defaultPort;
        $portSegment = $port !== null ? sprintf(';port=%s', $port) : '';

        if ($driver === 'pgsql') {
            foreach (['host', 'database'] as $key) {
                if (str_contains((string) ($config[$key] ?? ''), ';')) {
                    throw new DatabaseException('Invalid PostgreSQL connection parameter: ' . $key);
                }
            }

            return sprintf(
                'pgsql:host=%s;dbname=%s%s',
                $config['host'] ?? '127.0.0.1',
                $config['database'] ?? '',
                $portSegment,
            );
        }
        if ($driver !== 'mysql') {
            throw new DatabaseException('Unsupported PDO driver: ' . $driver);
        }

        return sprintf(
            'mysql:host=%s;dbname=%s%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['database'] ?? '',
            $portSegment,
            $config['charset'] ?? 'utf8mb4',
        );
    }

    /**
     * DSN für SQLite, unterstützt Memory & Datei.
     */
    protected function buildSqliteDsn(array $config): string
    {
        $database = $config['database'] ?? ':memory:';

        return $database === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $database;
    }

    /**
     * Liefert den Standard-Port für einen bekannten Treiber.
     */
    private function defaultPortForDriver(string $driver): ?int
    {
        return match ($driver) {
            'mysql', 'mysqli'   => 3306,
            'pgsql', 'postgres' => 5432,
            'sqlsrv'            => 1433,
            default             => null,
        };
    }

    /**
     * Standardmäßige PDO-Options.
     */
    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
    }
}
