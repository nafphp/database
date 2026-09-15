<?php

namespace Tests\Fixtures\Migrations;

use Naf\Database\Core\AbstractMigration;
use PDO;
use RuntimeException;

final class M003Fail extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $connection->exec('CREATE TABLE partial_items(id INTEGER)');
        throw new RuntimeException('Intentional failure');
    }

    public function down(PDO $connection): void
    {
    }
}
