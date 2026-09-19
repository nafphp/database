<?php

declare(strict_types=1);

namespace Tests\Unit;

use Naf\Database\Support\MigrationRegistry;
use PHPUnit\Framework\TestCase;

use function Naf\app;

/**
 * Where an application's own migrations are looked for.
 *
 * Both layouts are conventional: an application keeps its code in app/ or in
 * src/, and naf/framework already accepts either for plugins.php. A host built
 * the second way used to have nowhere conventional to put its migrations, and
 * the failure was silent -- addPath ignores a directory that is not there, so
 * the migrations were simply never seen.
 *
 * The fixtures for both directories are empty on purpose. What is at stake is
 * whether the path is registered at all; what a runner then finds inside is
 * MigrationRunnerTest's business.
 */
final class ConventionalMigrationPathsTest extends TestCase
{
    public function testBothLayoutsAreRegistered(): void
    {
        $base  = app()->getBasePath();
        $paths = MigrationRegistry::getPaths();

        self::assertContains($base . '/app/Migrations', $paths, 'the app/ layout');
        self::assertContains($base . '/src/Migrations', $paths, 'the src/ layout');
    }

    public function testAppComesFirst(): void
    {
        $base  = app()->getBasePath();
        $paths = array_values(MigrationRegistry::getPaths());

        self::assertLessThan(
            array_search($base . '/src/Migrations', $paths, true),
            array_search($base . '/app/Migrations', $paths, true),
            'a project holding both keeps the order it had before src/ was accepted',
        );
    }

    public function testADirectoryThatIsNotThereIsNotRegistered(): void
    {
        $paths = MigrationRegistry::getPaths();

        foreach ($paths as $path) {
            self::assertDirectoryExists($path);
        }
    }
}
