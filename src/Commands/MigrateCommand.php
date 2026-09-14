<?php

declare(strict_types=1);

namespace Naf\Database\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use Naf\CLI\Exception\ConsoleException;
use Naf\Database\Core\MigrationRunner;
use Naf\Database\Support\MigrationRegistry;

use function Naf\Database\database;

class MigrateCommand extends AbstractCommand
{
    public const string NAME = 'db:migrate';

    protected function configure(): void
    {
        $this->setTitle('Execute Migrations')
            ->setDescription('Execute migrations in either direction.')
            ->addArgument('direction')
            ->addOption('name', 'n');
    }

    public function run(Input $input, Output $output): int
    {
        $direction = $input->getArgument('direction');
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new ConsoleException('Invalid direction given.');
        }
        $connection = database();
        if ($connection === null) {
            $output->writeLine('Database connection not found.', 'error');

            return self::ERROR;
        }
        $option   = $input->getOption('name');
        $name     = is_array($option) ? $option[0] ?? null : $option;
        $executed = (new MigrationRunner($connection))->run(
            MigrationRegistry::getPaths(),
            $direction,
            is_string($name) ? $name : null,
        );
        foreach ($executed as $class) {
            $output->writeLine($direction . ' ' . $class, 'ok');
        }
        $output->writeLine(
            sprintf('%d migration(s) successfully executed.', count($executed)),
            'ok',
        );

        return self::SUCCESS;
    }
}
