<?php
namespace Tests\Fixtures\Migrations;
final class M001Parent extends \Naf\Database\Core\AbstractMigration {
public function up(\PDO $connection): void { $connection->exec('CREATE TABLE parent_items(id INTEGER PRIMARY KEY)'); }
public function down(\PDO $connection): void { $connection->exec('DROP TABLE parent_items'); }
}
