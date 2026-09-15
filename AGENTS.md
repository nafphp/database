# Working on naf/database

NAF is a small PHP framework with optional Composer plugins. Its core owns boot,
configuration, the service container, routing, events and PSR-7 responses. Prefer existing
NAF helpers, services and extension interfaces; keep application business rules in the host.
This package declares `type: naf-plugin` and is discovered after installation in a NAF host.
The plugin repository itself is not the application's web root.

Before changing code, read the [shared contribution workflow](https://github.com/nafphp/docs/blob/main/AGENT_WORKFLOW.md)
and [release procedure](https://github.com/nafphp/docs/blob/main/RELEASING.md).
In the multi-repository workspace, the same documents are in the sibling `docs/` checkout;
use the linked copies when working from a standalone clone. Preserve other contributors' work.
Review and update user documentation with every behavior change. Source fixes use an RC branch;
verified documentation-only changes can be merged and published by the agent.

## What this plugin does

`naf/database` supplies a configured PDO connection and migrations. Install with
`composer require naf/database`; configure `database` in the host's `app/config.php` and
install the PDO driver needed by that database. `naf/orm` is an optional layer above this
package, not a prerequisite for plain SQL.

## Use it

After configuring the connection and migrating a `products` table with `id` and `name`:

```php
<?php
use function Naf\Database\database;

$pdo = database() ?? throw new LogicException('Database is not configured.');
$statement = $pdo->prepare('SELECT id, name FROM products WHERE id = :id');
$statement->execute(['id' => 1]);
$product = $statement->fetch();
```

The helper returns `?PDO`, not a query builder. Error mode is exceptions and rows default
to associative arrays. Use bound parameters for values and allow-lists for dynamic identifiers.
The 0.2.2 candidate lazily binds `PDO::class` to this same connection unless already bound.

## Change it here

[Database](src/Core/Database.php) owns connection construction; [bootstrap](bootstrap.php)
binds it and registers migration paths. Follow [migrations](src/Support/MigrationRegistry.php)
and [commands](src/Commands/) for schema work. Host migrations live in `app/Migrations`.
Install `naf/cli` to use `vendor/bin/naf db:migration:create` and `vendor/bin/naf db:migrate up`.
The direction is passed once. Do not copy another plugin's migrations into the host.

## Verify

Run `composer test` and `composer validate --strict`. Use the [tests](tests/) for PDO/DSN
fixtures. Test SQL or transaction behavior against the affected database engine; SQLite alone
cannot establish PostgreSQL/MySQL concurrency behavior. Verify migrations and rollback on
throwaway databases. No `analyse` script is declared.

User docs: [Database](https://nafphp.github.io/docs/database/).
