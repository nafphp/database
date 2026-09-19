<?php

declare(strict_types=1);

namespace Tests\Unit;

use Naf\Database\Support\MigrationRegistry;
use Tests\NafTestCase;

class MigrationRegistryTest extends NafTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetRegistry();
    }

    protected function tearDown(): void
    {
        $this->resetRegistry();
        parent::tearDown();
    }

    public function testAddPathIgnoresMissingDirectory(): void
    {
        MigrationRegistry::addPath(__DIR__ . '/does-not-exist');

        $this->assertSame([], MigrationRegistry::getPaths());
    }

    public function testAddPathRegistersUniquePaths(): void
    {
        $base   = sys_get_temp_dir() . '/migration_registry_' . uniqid();
        $second = $base . '_extra';

        mkdir($base, 0755, true);
        mkdir($second, 0755, true);

        try {
            MigrationRegistry::addPath($base);
            MigrationRegistry::addPath($second . '/');
            MigrationRegistry::addPath($base);

            $this->assertSame([$base, $second], MigrationRegistry::getPaths());
        } finally {
            if (is_dir($second)) {
                rmdir($second);
            }

            if (is_dir($base)) {
                rmdir($base);
            }
        }
    }

    private function resetRegistry(): void
    {
        MigrationRegistry::reset();
    }
}
