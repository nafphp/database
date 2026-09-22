<?php

declare(strict_types=1);

namespace Naf\Database\Core;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** A single ordered plan across all registered plugin/application paths. */
final class MigrationRunner
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return list<string> Executed fully qualified migration names. */
    public function run(array $paths, string $direction, ?string $name = null): array
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Invalid migration direction.');
        }
        if ($this->connection->inTransaction()) {
            throw new RuntimeException('Run migrations outside application transactions.');
        }
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported migration driver.');
        }
        $locked = false;

        try {
            if ($driver === 'pgsql') {
                $this->connection->query('SELECT pg_advisory_lock(78236491)')->closeCursor();
                $locked = true;
            } elseif ($driver === 'mysql') {
                $locked = (int) $this->connection
                    ->query(
                        "SELECT GET_LOCK(CONCAT('naf:migrations:', LEFT(SHA2(DATABASE(), 256), 40)), 10)",
                    )
                    ->fetchColumn() === 1;
                if (!$locked) {
                    throw new RuntimeException('Another migration runner holds the database lock.');
                }
            }
            $files = $this->discover($paths);
            $this->ensureTracker($driver);
            $this->upgradeLegacyNames($files);
            $applied = $this->connection
                ->query('SELECT name FROM migrations ORDER BY id')
                ->fetchAll(PDO::FETCH_COLUMN);
            if ($direction === 'down') {
                $ordered = [];
                foreach (array_reverse($applied) as $class) {
                    if (!isset($files[$class])) {
                        if ($name === null || $name === $class) {
                            throw new RuntimeException(
                                'Applied migration source is missing: ' . $class,
                            );
                        }
                        continue;
                    }
                    $ordered[$class] = $files[$class];
                }
                $files = $ordered;
            }
            if ($name !== null) {
                $files = array_filter(
                    $files,
                    static fn($file, $class) => $class === $name
                        || basename($file, '.php') === $name,
                    ARRAY_FILTER_USE_BOTH,
                );
                if (count($files) !== 1) {
                    throw new RuntimeException(
                        'Migration name must select exactly one migration: ' . $name,
                    );
                }
            }
            $executed = [];
            foreach ($files as $class => $file) {
                if ($direction === 'up' && in_array($class, $applied, true)) {
                    continue;
                }
                $migration = new $class();
                if (!$migration->shouldRun()) {
                    continue;
                }
                // PostgreSQL/SQLite DDL and tracker are atomic. MySQL DDL commits implicitly;
                // leave a failed migration untracked and require its DDL to be restartable.
                $transactional = $driver !== 'mysql';
                if ($transactional) {
                    $this->connection->beginTransaction();
                }

                try {
                    $migration->$direction($this->connection);
                    $statement = $this->connection->prepare(
                        $direction === 'up'
                            ? 'INSERT INTO migrations (name) VALUES (?)'
                            : 'DELETE FROM migrations WHERE name = ?',
                    );
                    $statement->execute([$class]);
                    if ($transactional) {
                        $this->connection->commit();
                    }
                    $executed[] = $class;
                } catch (Throwable $error) {
                    if ($transactional && $this->connection->inTransaction()) {
                        $this->connection->rollBack();
                    }
                    throw new RuntimeException(
                        'Migration failed: ' . $class . ': ' . $error->getMessage(),
                        0,
                        $error,
                    );
                }
            }

            return $executed;
        } finally {
            if ($locked && $driver === 'pgsql') {
                $this->connection->query('SELECT pg_advisory_unlock(78236491)')->closeCursor();
            }
            if ($locked && $driver === 'mysql') {
                $this->connection
                    ->query(
                        "SELECT RELEASE_LOCK(CONCAT('naf:migrations:', LEFT(SHA2(DATABASE(), 256), 40)))",
                    )
                    ->closeCursor();
            }
        }
    }

    /**
     * Read pending migrations without running them or modifying the tracker.
     * Requires an existing migrations table; connection/tracker errors propagate.
     * Unknown applied names are retained for packages that have been uninstalled.
     *
     * @return list<class-string<MigrationInterface>>
     */
    public function pending(array $paths): array
    {
        $files   = $this->discover($paths);
        $applied = $this->connection->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($applied as &$name) {
            if (str_contains($name, '\\')) {
                continue;
            }
            $matches = array_keys(array_filter($files, static fn($file) => basename($file, '.php') === $name));
            if (count($matches) !== 1) {
                throw new RuntimeException('Legacy migration identity cannot be resolved uniquely: ' . $name);
            }
            $name = $matches[0];
        }
        unset($name);

        return array_values(array_filter(
            array_keys($files),
            static fn($class) => !in_array($class, $applied, true) && (new $class())->shouldRun(),
        ));
    }

    /** @return array<class-string<MigrationInterface>,string> */
    private function discover(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            foreach (glob(rtrim($path, '/\\') . '/*.php') ?: [] as $file) {
                $real = realpath($file);
                if ($real !== false && is_file($real)) {
                    $files[$real] = $real;
                }
            }
        }
        // Basename then FQCN gives deterministic global order independent of registry order.
        $migrations = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if (
                $source === false
                || !preg_match('/^\s*namespace\s+([^;{]+)\s*[;{]/m', $source, $matches)
            ) {
                continue;
            }
            $class = trim($matches[1]) . '\\' . basename($file, '.php');
            if (isset($migrations[$class]) && $migrations[$class] !== $file) {
                throw new RuntimeException('Duplicate migration identity: ' . $class);
            }
            require_once $file;
            if (
                !class_exists($class, false)
                || !is_subclass_of($class, MigrationInterface::class)
            ) {
                continue;
            }
            if (strlen($class) > 255) {
                throw new RuntimeException('Migration identity exceeds 255 characters: ' . $class);
            }
            if (isset($migrations[$class]) && $migrations[$class] !== $file) {
                throw new RuntimeException('Duplicate migration identity: ' . $class);
            }
            $migrations[$class] = $file;
        }
        uksort($migrations, static function ($a, $b) use ($migrations): int {
            return [basename($migrations[$a]), $a] <=> [basename($migrations[$b]), $b];
        });

        return $migrations;
    }

    private function ensureTracker(string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'BIGSERIAL PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS migrations (id $id, name VARCHAR(255) NOT NULL, createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, executedAt TIMESTAMP NULL)",
        );
        if ($driver === 'mysql') {
            $this->connection->exec('ALTER TABLE migrations MODIFY name VARCHAR(255) NOT NULL');
        }
        $duplicates = $this->connection
            ->query('SELECT name FROM migrations GROUP BY name HAVING COUNT(*) > 1')
            ->fetchAll(PDO::FETCH_COLUMN);
        if ($duplicates !== []) {
            throw new RuntimeException(
                'Duplicate migration history requires manual reconciliation: '
                    . implode(', ', $duplicates),
            );
        }
        if ($driver === 'mysql') {
            $exists = $this->connection
                ->query(
                    "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'migrations' AND index_name = 'migrations_name_unique'",
                )
                ->fetchColumn();
            if (!(int) $exists) {
                $this->connection->exec(
                    'CREATE UNIQUE INDEX migrations_name_unique ON migrations (name)',
                );
            }
        } else {
            $this->connection->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS migrations_name_unique ON migrations (name)',
            );
        }
    }

    private function upgradeLegacyNames(array $files): void
    {
        foreach (
            $this->connection->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN) as $legacy
        ) {
            if (str_contains($legacy, '\\')) {
                continue;
            }
            $matches = array_keys(
                array_filter($files, static fn($file) => basename($file, '.php') === $legacy),
            );
            if (count($matches) !== 1) {
                throw new RuntimeException(
                    'Legacy migration identity cannot be resolved uniquely: ' . $legacy,
                );
            }
            $this->connection
                ->prepare('UPDATE migrations SET name = ? WHERE name = ?')
                ->execute([$matches[0], $legacy]);
        }
    }
}
