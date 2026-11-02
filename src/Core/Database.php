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
        $options  = $this->pdoOptions($config['driver'] ?? 'mysql');
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        try {
            $pdoFactory ??= static fn($dsn, $username, $password, $options) => new PDO($dsn, $username, $password, $options);
            $this->pdo = $pdoFactory($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new DatabaseException('Database connection failed.', 0, $e);
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

        foreach (['host', 'database', 'port', 'charset'] as $key) {
            if (strpbrk((string) ($config[$key] ?? ''), ";\0") !== false) {
                throw new DatabaseException('Invalid connection parameter: ' . $key);
            }
        }

        $defaultPort = $this->defaultPortForDriver($driver);
        $port        = $config['port'] ?? $defaultPort;
        $portSegment = $port !== null ? sprintf(';port=%s', $port) : '';

        if ($driver === 'pgsql') {
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
    private function pdoOptions(string $driver): array
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver === 'mysql') {
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        return $options;
    }
}
