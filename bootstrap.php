<?php

declare(strict_types=1);

use Naf\CLI\Support\CommandRegistry;
use Naf\Database\Commands\MigrateCommand;
use Naf\Database\Commands\MigrationCreateCommand;
use Naf\Database\Core\Database;
use Naf\Database\Exceptions\DatabaseException;
use Naf\Database\Support\MigrationRegistry;

use function Naf\app;
use function Naf\config;

app()
    ->container()
    ->set(Database::class, function () {
        $config = config('database');
        if (!$config) {
            return null;
        }

        return new Database($config);
    });

// A concrete PDO dependency requires a connection; the public helper remains nullable.
if (!app()->container()->has(PDO::class)) {
    app()
        ->container()
        ->set(PDO::class, static function ($container): PDO {
            $database = $container->get(Database::class);
            if (!($database instanceof Database)) {
                throw new DatabaseException(
                    'A PDO connection is required, but database configuration is missing.',
                );
            }

            return $database->getConnection();
        });
}

// Both layouts, in that order. An application keeps its code in app/ or in
// src/ -- naf/framework already accepts either for plugins.php, and a host laid
// out the second way had no conventional place for its migrations until now.
// addPath ignores a directory that is not there, so a project only ever
// registers the one it actually has.
foreach (['/app/Migrations', '/src/Migrations'] as $conventional) {
    MigrationRegistry::addPath(app()->getBasePath() . $conventional);
}

$migrationPaths = config('database:migrationPaths');

if (is_array($migrationPaths)) {
    foreach ($migrationPaths as $path) {
        MigrationRegistry::addPath($path);
    }
}

if (app()->hasPlugin('naf/cli')) {
    $commandRegistry = app()->container()->get(CommandRegistry::class);
    $commandRegistry->add(MigrateCommand::class);
    $commandRegistry->add(MigrationCreateCommand::class);
}
