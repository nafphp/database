<?php
declare(strict_types=1);
namespace Tests\Unit;
use Naf\Database\Core\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
final class MigrationRunnerTest extends TestCase
{
    public function testGlobalOrderReverseDownAndFullyQualifiedIdentity(): void
    {
        $pdo=new PDO('sqlite::memory:'); $runner=new MigrationRunner($pdo);
        $paths=[__DIR__.'/../Fixtures/Migrations/second',__DIR__.'/../Fixtures/Migrations/first'];
        $names=$runner->run($paths,'up');
        $this->assertSame(['Tests\\Fixtures\\Migrations\\M001Parent','Tests\\Fixtures\\Migrations\\M002Child'],$names);
        $this->assertSame([],$runner->run($paths,'up'));
        $this->assertSame(array_reverse($names),$runner->run($paths,'down'));
        $this->assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
    }
    public function testLegacyNamesAreUpgradedWithoutReplayingMigration(): void
    {
        $pdo=new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE migrations(id INTEGER PRIMARY KEY,name VARCHAR(32)); INSERT INTO migrations(name) VALUES('M001Parent'); CREATE TABLE parent_items(id INTEGER PRIMARY KEY)");
        $runner=new MigrationRunner($pdo);
        $this->assertSame([],$runner->run([__DIR__.'/../Fixtures/Migrations/first'],'up'));
        $this->assertSame('Tests\\Fixtures\\Migrations\\M001Parent',$pdo->query('SELECT name FROM migrations')->fetchColumn());
    }
    public function testFailedMigrationLeavesNoTrackerOrPartialSqliteDdl(): void
    {
        $pdo=new PDO('sqlite::memory:'); $runner=new MigrationRunner($pdo);
        try { $runner->run([__DIR__.'/../Fixtures/Migrations/failing'],'up'); $this->fail('Expected failure'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('M003Fail',$e->getMessage()); }
        $this->assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        $this->assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='partial_items'")->fetchColumn());
    }
}
