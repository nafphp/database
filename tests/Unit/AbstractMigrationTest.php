<?php

declare(strict_types=1);

namespace Tests\Unit;

use Naf\Database\Core\AbstractMigration;
use Tests\NafTestCase;

class AbstractMigrationTest extends NafTestCase
{
    public function testDefaultShouldRunReturnsTrue(): void
    {
        $migration = new class extends AbstractMigration {
            public function up(\PDO $connection): void
            {
            }

            public function down(\PDO $connection): void
            {
            }
        };

        $this->assertTrue($migration->shouldRun());
    }

    public function testCanOverrideShouldRun(): void
    {
        $migration = new class extends AbstractMigration {
            public function up(\PDO $connection): void
            {
            }

            public function down(\PDO $connection): void
            {
            }

            public function shouldRun(): bool
            {
                return false;
            }
        };

        $this->assertFalse($migration->shouldRun());
    }
}
