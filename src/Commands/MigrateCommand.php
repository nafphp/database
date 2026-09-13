<?php

declare(strict_types=1);

namespace Naf\Database\Commands;

use Naf\Database\Core\MigrationInterface;
use Naf\Database\Support\MigrationRegistry;
use PDO;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CLI\Exception\ConsoleException;
use Naf\CLI\Core\AbstractCommand;
use function Naf\config;
use function Naf\Database\database;

class MigrateCommand extends AbstractCommand
{

    public const string NAME = 'db:migrate';

    private int $executedMigrations = 0;

    protected function configure(): void
    {
        $this
            ->setTitle('Execute Migrations')
            ->setDescription('Execute migrations in either direction.')
            ->addArgument('direction')
            ->addOption('name', 'n');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     * @throws ConsoleException
     */
    public function run(Input $input, Output $output): int
    {
        $direction = $input->getArgument('direction');

        if (false === \in_array($direction, ['up', 'down'])) {
            throw new ConsoleException('Invalid direction given.');
        }

        $connection = database();

        if (null === $connection) {
            $output->writeLine('Database connection not found.');
            return static::ERROR;
        }

        $this->ensureMigrationTrackingIntegrity($connection, $output);
        $paths = MigrationRegistry::getPaths();

        $files = [];

        foreach ($paths as $path) {
            $pattern = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.php';
            $found = glob($pattern);

            if (false === $found) {
                continue;
            }

            foreach ($found as $filePath) {
                if (!is_file($filePath)) {
                    continue;
                }

                $files[] = $filePath;
            }
        }

        $existingMigrations = $connection
            ->query('SELECT `name` from `migrations`')
            ->fetchAll(\PDO::FETCH_COLUMN);

        // Override files with a migration file from an argument option when given
        if ($input->getOption('name')) {
            $name = $input->getOption('name')[0] . '.php';
            $files = array_values(array_filter($files, static function (string $filePath) use ($name) {
                return basename($filePath) === $name;
            }));
        }

        foreach ($files as $filePath) {
            $className = basename($filePath, '.php');
            $content = file_get_contents($filePath);

            if (false === $content) {
                continue;
            }

            if (!preg_match('/^namespace\s+(.+);/m', $content, $matches)) {
                continue;
            }

            $namespace = trim($matches[1]);
            $migrationClass = sprintf('%s\\%s', $namespace, $className);

            require_once $filePath;

            if (
                ($direction !== 'down' && \in_array($className, $existingMigrations, true))
                || ($direction === 'down' && !\in_array($className, $existingMigrations, true))
                || false === class_exists($migrationClass, false)
            ) {
                continue;
            }

            $migrationObject = new $migrationClass();

            if (!$migrationObject instanceof MigrationInterface) {
                $output->writeLine('Migration class must implement MigrationInterface.', 'error');
                continue;
            }

            if (false === $migrationObject->shouldRun()) {
                continue;
            }

            $result = $this->executeMigration($migrationClass, $migrationObject, $direction, $output);

            if (false === $result) {
                continue;
            }

            $output->writeLine(sprintf('✔ %s %s executed', $className, $direction));
        }

        if (0 === $this->executedMigrations) {
            $output->writeLine('No migrations executed.', 'warning');
        } else {
            $output->writeEmptyLine();
            $output->writeLine(
                sprintf(
                    '%d migration(s) successfully executed.',
                    $this->executedMigrations
                ),
                'ok'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Execute a migration instance.
     *
     * @param string $migration
     * @param MigrationInterface $object
     * @param string $direction
     * @param Output $output
     * @return bool
     */
    private function executeMigration(string $migration, MigrationInterface $object, string $direction, Output $output): bool
    {
        $connection = database();

        if (false === $connection instanceof PDO) {
            return false;
        }

        try {
            $object->$direction($connection);
        } catch (\Throwable $t) {
            $output->writeLine($t->getMessage() . ' in ' . $migration, 'error');
            throw $t;
        }


        ++$this->executedMigrations;

        $name = substr($migration, strrpos($migration, '\\') + 1);

        switch ($direction) {
            case 'up':
                $stmt = $connection->prepare("INSERT INTO `migrations` (`name`) VALUES (?)");
                $stmt->execute([$name]);
                break;
            case 'down':
                $stmt = $connection->prepare("DELETE FROM `migrations` WHERE `name` = ?");
                $stmt->execute([$name]);
        }

        return true;
    }

    private function ensureMigrationTrackingIntegrity(\PDO $connection, Output $output): void
    {
        try {
            $query = $connection->query('SELECT * FROM `migrations`');
            $query->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            $output->writeLine('(!) Creating migration table as it does not exist.', 'warning');
            $output->writeEmptyLine();

            if (config('database:driver') === 'sqlite') {

                $connection->exec(<<<SQL
                CREATE TABLE `migrations` (
                    id INTEGER,
                    name VARCHAR(255) NOT NULL,
                    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                    executedAt DATETIME NULL,
                    CONSTRAINT
                        migration_pk
                        PRIMARY KEY (id)
                )
                SQL
                );

            } else {

                $connection->exec(<<<SQL
                CREATE TABLE `migrations` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(32) NOT NULL,
                    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                    executedAt DATETIME NULL
                )
                SQL);

            }
        }

    }
}