<?php

namespace Tests\Fixtures\Migrations;

use Naf\Database\Core\AbstractMigration;
use PDO;

final class M002Child extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $connection->exec(
            'CREATE TABLE child_items(id INTEGER PRIMARY KEY,parent_id INTEGER REFERENCES parent_items(id))',
        );
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE child_items');
    }
}
