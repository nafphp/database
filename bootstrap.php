<?php

declare(strict_types=1);

use Naf\CLI\Support\CommandRegistry;
use Naf\Database\Commands\MigrateCommand;
use Naf\Database\Commands\MigrationCreateCommand;
use Naf\Database\Core\Database;
use Naf\Database\Support\MigrationRegistry;
use function Naf\app;
use function Naf\config;

app()->container()->set(Database::class, function() {
    $config = config('database');
    if (!$config) return null;
    return new Database($config);
});

MigrationRegistry::addPath(app()->getBasePath() . '/app/Migrations');

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