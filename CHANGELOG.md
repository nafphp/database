# Changelog

## [0.1.2] - 2026-02-21

### Added
- Introduced `MigrationRegistry` plus `AbstractMigration` so migrations from multiple plugins can be registered and share the optional `shouldRun()` hook.
- Added driver-specific default ports for newer drivers (MySQL, PostgreSQL, SQL Server) when building a DSN.

### Changed
- `MigrateCommand` now scans every registered migration directory with `glob()`, loads each file before instantiating the class, and only runs migrations whose `shouldRun()` returns `true`.
- Generated migrations now extend `AbstractMigration` and inherit a default `shouldRun()` that can be overridden when conditional execution is required.

### Breaking Changes
- `MigrationInterface` now requires a `shouldRun()` implementation, which means any class that implemented the interface directly must either implement the method or extend `AbstractMigration`. The minimum change looks like this:
  ```php
  use PDO;
  use Naf\Database\Core\MigrationInterface;

  class LegacyMigration implements MigrationInterface
  {
      public function up(PDO $connection): void {}

      public function down(PDO $connection): void {}

      public function shouldRun(): bool
      {
          return true;
      }
  }
  ```
