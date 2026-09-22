<?php

declare(strict_types=1);

namespace Tests\Fixtures\Migrations;

use LogicException;
use Naf\Database\Core\AbstractMigration;
use PDO;

final class M004Skipped extends AbstractMigration
{
    public function shouldRun(): bool
    {
        return false;
    }

    public function up(PDO $connection): void
    {
        throw new LogicException('Not applicable.');
    }

    public function down(PDO $connection): void
    {
    }
}
