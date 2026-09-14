<?php
namespace Tests\Fixtures\Migrations;
final class M003Fail extends \Naf\Database\Core\AbstractMigration {
public function up(\PDO $connection): void { $connection->exec('CREATE TABLE partial_items(id INTEGER)'); throw new \RuntimeException('Intentional failure'); }
public function down(\PDO $connection): void {  }
}
